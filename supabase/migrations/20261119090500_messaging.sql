-- SwapFix 5/8: two-party, context-linked conversations and messages.

create table public.conversations (
    id uuid primary key default gen_random_uuid(),
    context_type text not null check (context_type in ('listing', 'offer', 'repair')),
    listing_id uuid references public.product_listings (id),
    offer_id uuid references public.trade_offers (id),
    repair_id uuid references public.repair_requests (id),
    initiator_id uuid not null references public.profiles (id),
    recipient_id uuid not null references public.profiles (id),
    context_title text not null check (char_length(context_title) between 1 and 200),
    initiator_unread integer not null default 0 check (initiator_unread >= 0),
    recipient_unread integer not null default 0 check (recipient_unread >= 0),
    last_message_preview text not null default '' check (char_length(last_message_preview) <= 160),
    last_message_at timestamptz,
    created_at timestamptz not null default now(),
    constraint conversations_not_self check (initiator_id <> recipient_id),
    constraint conversations_one_context check (
        (context_type = 'listing' and listing_id is not null and offer_id is null and repair_id is null)
        or (context_type = 'offer' and offer_id is not null and listing_id is null and repair_id is null)
        or (context_type = 'repair' and repair_id is not null and listing_id is null and offer_id is null)
    ),
    unique (listing_id, initiator_id),
    unique (offer_id),
    unique (repair_id)
);
create index conversations_initiator_idx on public.conversations (initiator_id, last_message_at desc nulls last);
create index conversations_recipient_idx on public.conversations (recipient_id, last_message_at desc nulls last);

create table public.messages (
    id uuid primary key default gen_random_uuid(),
    conversation_id uuid not null references public.conversations (id) on delete cascade,
    sender_id uuid not null references public.profiles (id),
    body text not null check (char_length(body) between 1 and 4000),
    created_at timestamptz not null default now()
);
create index messages_conversation_idx on public.messages (conversation_id, created_at desc);
create index messages_sender_idx on public.messages (sender_id);
create trigger messages_immutable before update on public.messages
for each row execute function swapfix_private.forbid_update();

create function swapfix_private.ensure_conversation(
    p_type text, p_context uuid, p_initiator uuid, p_recipient uuid, p_title text
) returns uuid
language plpgsql set search_path = '' as $$
declare
    v_id uuid;
begin
    insert into public.conversations (context_type, listing_id, offer_id, repair_id, initiator_id, recipient_id, context_title)
    values (
        p_type,
        case when p_type = 'listing' then p_context end,
        case when p_type = 'offer' then p_context end,
        case when p_type = 'repair' then p_context end,
        p_initiator, p_recipient, left(p_title, 200)
    )
    on conflict do nothing
    returning id into v_id;
    if v_id is null then
        select id into v_id from public.conversations
        where (p_type = 'listing' and listing_id = p_context and initiator_id = p_initiator)
           or (p_type = 'offer' and offer_id = p_context)
           or (p_type = 'repair' and repair_id = p_context);
    end if;
    return v_id;
end;
$$;

create function swapfix_private.post_message(p_conversation uuid, p_sender uuid, p_body text, p_notify boolean) returns uuid
language plpgsql set search_path = '' as $$
declare
    v_conversation public.conversations;
    v_body text := swapfix_private.clean_text(p_body, 'Message', 1, 4000);
    v_recipient uuid;
    v_sender_name text;
    v_id uuid;
begin
    select * into v_conversation from public.conversations where id = p_conversation for update;
    if not found or p_sender not in (v_conversation.initiator_id, v_conversation.recipient_id) then
        perform swapfix_private.fail('SF404', 'Conversation not found.');
    end if;
    insert into public.messages (conversation_id, sender_id, body) values (p_conversation, p_sender, v_body)
    returning id into v_id;
    v_recipient := case when p_sender = v_conversation.initiator_id then v_conversation.recipient_id else v_conversation.initiator_id end;
    update public.conversations set
        last_message_at = now(),
        last_message_preview = left(v_body, 160),
        initiator_unread = initiator_unread + (v_recipient = initiator_id)::integer,
        recipient_unread = recipient_unread + (v_recipient = recipient_id)::integer
    where id = p_conversation;
    if p_notify then
        select display_name into v_sender_name from public.profiles where id = p_sender;
        perform swapfix_private.notify(
            v_recipient, 'message', 'New message from ' || v_sender_name,
            v_conversation.context_title || ': ' || left(v_body, 120),
            '/messages/' || p_conversation, 'conversation', p_conversation
        );
    end if;
    return v_id;
end;
$$;

create function swapfix_private.api_start_listing_inquiry(p_listing_id uuid, p_message text) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_listing public.product_listings;
    v_conversation uuid;
begin
    select * into v_listing from public.product_listings where id = p_listing_id for share;
    if not found or v_listing.status not in ('available', 'reserved') then
        perform swapfix_private.fail('SF404', 'Listing not found.');
    end if;
    if v_listing.owner_id = v_user then
        perform swapfix_private.fail('SF403', 'You cannot send an inquiry to your own listing.');
    end if;
    v_conversation := swapfix_private.ensure_conversation(
        'listing', p_listing_id, v_user, v_listing.owner_id, v_listing.title
    );
    perform swapfix_private.post_message(v_conversation, v_user, p_message, true);
    return jsonb_build_object('conversation_id', v_conversation);
end;
$$;

create function swapfix_private.api_send_message(p_conversation_id uuid, p_body text) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
begin
    return jsonb_build_object('id', swapfix_private.post_message(p_conversation_id, v_user, p_body, true));
end;
$$;

create function swapfix_private.api_mark_conversation_read(p_conversation_id uuid) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_user();
begin
    update public.conversations set
        initiator_unread = case when initiator_id = v_user then 0 else initiator_unread end,
        recipient_unread = case when recipient_id = v_user then 0 else recipient_unread end
    where id = p_conversation_id and v_user in (initiator_id, recipient_id);
    if not found then
        perform swapfix_private.fail('SF404', 'Conversation not found.');
    end if;
    update public.notifications set read_at = now()
    where recipient_id = v_user and kind = 'message' and related_id = p_conversation_id and read_at is null;
    return jsonb_build_object('id', p_conversation_id);
end;
$$;

-- One round trip for the signed-in shell: stored profile plus unread counts.
create function public.sf_session() returns jsonb
language sql stable security invoker set search_path = '' as $$
    select case when auth.uid() is null then null else jsonb_build_object(
        'user_id', auth.uid(),
        'profile', (
            select to_jsonb(p) || jsonb_build_object(
                'location', coalesce(d.location, ''),
                'email_notifications', coalesce(d.email_notifications, false)
            )
            from public.profiles p
            left join public.profile_private d on d.user_id = p.id
            where p.id = auth.uid()
        ),
        'unread_notifications', (
            select count(*) from public.notifications n where n.recipient_id = auth.uid() and n.read_at is null
        ),
        'unread_messages', (
            select coalesce(sum(case when c.initiator_id = auth.uid() then c.initiator_unread else c.recipient_unread end), 0)
            from public.conversations c
            where auth.uid() in (c.initiator_id, c.recipient_id)
        )
    ) end;
$$;
