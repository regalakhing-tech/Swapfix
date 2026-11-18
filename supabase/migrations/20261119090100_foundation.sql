-- SwapFix 1/8: private helper schema, accounts, technician profiles, notifications.
--
-- Security model used by every migration in this folder:
--   * Exposed tables have RLS and receive SELECT grants only (see 8/8).
--   * Every write goes through a swapfix_private.api_* SECURITY DEFINER function that
--     derives the actor from auth.uid() and re-checks ownership and state under row locks.
--   * swapfix_private is not exposed by PostgREST. Clients reach it only through the
--     SECURITY INVOKER public.sf_* wrappers generated in 8/8.
--   * Business failures use SQLSTATE SF401/SF403/SF404/SF409/SF422 with a user-safe message.

create schema if not exists swapfix_private;
revoke all on schema swapfix_private from public;
grant usage on schema swapfix_private to anon, authenticated, service_role;
comment on schema swapfix_private is 'SwapFix internals. Never add this schema to the Data API exposed schemas.';

create function swapfix_private.fail(p_code text, p_message text) returns void
language plpgsql set search_path = '' as $$
begin
    raise exception using errcode = p_code, message = p_message;
end;
$$;

create function swapfix_private.require_user() returns uuid
language plpgsql stable set search_path = '' as $$
declare
    v_user uuid := auth.uid();
begin
    if v_user is null then
        perform swapfix_private.fail('SF401', 'Sign in to continue.');
    end if;
    return v_user;
end;
$$;

create function swapfix_private.clean_text(p_value text, p_label text, p_min integer, p_max integer) returns text
language plpgsql set search_path = '' as $$
declare
    v_text text := btrim(coalesce(p_value, ''), E' \t\r\n');
begin
    if char_length(v_text) < p_min then
        perform swapfix_private.fail('SF422', p_label || ' is required.');
    end if;
    if char_length(v_text) > p_max then
        perform swapfix_private.fail('SF422', p_label || ' must be ' || p_max || ' characters or fewer.');
    end if;
    return v_text;
end;
$$;

create function swapfix_private.clean_list(p_values text[], p_label text, p_max_items integer, p_max_length integer) returns text[]
language plpgsql set search_path = '' as $$
declare
    v_items text[];
begin
    select coalesce(array_agg(item order by position), '{}') into v_items
    from (
        select distinct on (lower(btrim(value))) btrim(value) as item, position
        from unnest(coalesce(p_values, '{}'::text[])) with ordinality as source(value, position)
        where btrim(coalesce(value, '')) <> ''
        order by lower(btrim(value)), position
    ) unique_items;
    if cardinality(v_items) > p_max_items then
        perform swapfix_private.fail('SF422', p_label || ' can contain at most ' || p_max_items || ' items.');
    end if;
    if exists (select 1 from unnest(v_items) item where char_length(item) > p_max_length) then
        perform swapfix_private.fail('SF422', p_label || ' items must be ' || p_max_length || ' characters or fewer.');
    end if;
    return v_items;
end;
$$;

create function swapfix_private.clean_money(p_value numeric, p_label text, p_minimum numeric) returns numeric
language plpgsql set search_path = '' as $$
begin
    if p_value is null or p_value < p_minimum or p_value >= 10000000000 then
        perform swapfix_private.fail('SF422', p_label || ' must be between ' || p_minimum || ' and 9,999,999,999.99.');
    end if;
    if p_value <> round(p_value, 2) then
        perform swapfix_private.fail('SF422', p_label || ' can have at most two decimal places.');
    end if;
    return round(p_value, 2);
end;
$$;

create function swapfix_private.valid_photo_paths(p_paths text[], p_owner uuid, p_max integer) returns boolean
language sql immutable set search_path = '' as $$
    select coalesce(cardinality(p_paths), 0) <= p_max
       and not exists (
            select 1 from unnest(coalesce(p_paths, '{}'::text[])) as path
            where path is null or path !~ ('^' || p_owner::text || '/[a-f0-9]{32}\.(jpg|png|webp)$')
       )
       and (select count(*) = count(distinct path) from unnest(coalesce(p_paths, '{}'::text[])) as path);
$$;

create function swapfix_private.clean_photo_paths(p_paths text[], p_owner uuid, p_max integer) returns text[]
language plpgsql set search_path = '' as $$
begin
    if not swapfix_private.valid_photo_paths(p_paths, p_owner, p_max) then
        perform swapfix_private.fail('SF422', 'Photos must be your own uploads, at most ' || p_max || ' per record.');
    end if;
    return coalesce(p_paths, '{}'::text[]);
end;
$$;

-- Search helpers run inside SECURITY INVOKER read functions, including for anon.
create function swapfix_private.like_pattern(p_value text) returns text
language sql immutable set search_path = '' as $$
    select '%' || replace(replace(replace(btrim(p_value), '\', '\\'), '%', '\%'), '_', '\_') || '%';
$$;

create function swapfix_private.prefix_tsquery(p_value text) returns tsquery
language sql immutable set search_path = '' as $$
    select coalesce(
        (select to_tsquery('simple'::regconfig, string_agg(quote_literal(term) || ':*', ' & '))
         from unnest(regexp_split_to_array(lower(left(coalesce(p_value, ''), 100)), '[^[:alnum:]]+')) as term
         where term <> ''),
        ''::tsquery
    );
$$;

create function swapfix_private.touch_updated_at() returns trigger
language plpgsql set search_path = '' as $$
begin
    new.updated_at := now();
    return new;
end;
$$;

create function swapfix_private.forbid_update() returns trigger
language plpgsql set search_path = '' as $$
begin
    raise exception using errcode = 'SF409', message = 'This record is permanent and cannot be changed.';
end;
$$;

-- Accounts. Only non-sensitive card fields live in profiles because it is publicly readable.
create table public.profiles (
    id uuid primary key references auth.users (id) on delete cascade,
    account_type text not null check (account_type in ('customer', 'technician')),
    display_name text not null check (char_length(display_name) between 2 and 80),
    avatar_path text,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint profiles_avatar_owned check (avatar_path is null or swapfix_private.valid_photo_paths(array[avatar_path], id, 1))
);
create trigger profiles_touch before update on public.profiles
for each row execute function swapfix_private.touch_updated_at();

create table public.profile_private (
    user_id uuid primary key references public.profiles (id) on delete cascade,
    location text not null default '' check (char_length(location) <= 120),
    email_notifications boolean not null default false,
    updated_at timestamptz not null default now()
);
create trigger profile_private_touch before update on public.profile_private
for each row execute function swapfix_private.touch_updated_at();

create table public.technician_profiles (
    user_id uuid primary key references public.profiles (id) on delete cascade,
    headline text not null default '' check (char_length(headline) <= 120),
    bio text not null default '' check (char_length(bio) <= 2000),
    location text not null default '' check (char_length(location) <= 120),
    service_area text not null default '' check (char_length(service_area) <= 200),
    available_hours text not null default '' check (char_length(available_hours) <= 200),
    specialties text[] not null default '{}',
    supported_brands text[] not null default '{}',
    supported_models text[] not null default '{}',
    rating_average numeric(3, 2) not null default 0 check (rating_average between 0 and 5),
    rating_count integer not null default 0 check (rating_count >= 0),
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);
create index technician_profiles_rating_idx on public.technician_profiles (rating_average desc, rating_count desc);
create index technician_profiles_specialties_idx on public.technician_profiles using gin (specialties);
create index technician_profiles_brands_idx on public.technician_profiles using gin (supported_brands);
create trigger technician_profiles_touch before update on public.technician_profiles
for each row execute function swapfix_private.touch_updated_at();

create function swapfix_private.require_profile() returns uuid
language plpgsql stable set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_user();
begin
    if not exists (select 1 from public.profiles where id = v_user) then
        perform swapfix_private.fail('SF403', 'Finish setting up your account first.');
    end if;
    return v_user;
end;
$$;

-- Authorization reads the stored account type, never JWT or user metadata.
create function swapfix_private.require_technician() returns uuid
language plpgsql stable set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_user();
begin
    if not exists (select 1 from public.profiles where id = v_user and account_type = 'technician') then
        perform swapfix_private.fail('SF403', 'Only technician accounts can do this.');
    end if;
    return v_user;
end;
$$;

-- Notifications and the optional email outbox.
create table public.notifications (
    id uuid primary key default gen_random_uuid(),
    recipient_id uuid not null references public.profiles (id) on delete cascade,
    actor_id uuid references public.profiles (id) on delete set null,
    kind text not null check (kind in (
        'message', 'trade_offer', 'trade_counter', 'trade_accepted', 'trade_declined', 'trade_update',
        'repair_request', 'quotation_sent', 'quotation_accepted', 'quotation_declined',
        'added_cost_requested', 'added_cost_accepted', 'added_cost_declined', 'repair_status', 'review'
    )),
    title text not null check (char_length(title) between 1 and 160),
    body text not null default '' check (char_length(body) <= 400),
    link_path text not null check (link_path ~ '^/[A-Za-z0-9/_-]*$'),
    related_type text not null check (related_type in ('listing', 'exchange', 'offer', 'repair', 'conversation')),
    related_id uuid not null,
    read_at timestamptz,
    created_at timestamptz not null default now()
);
create index notifications_recipient_idx on public.notifications (recipient_id, created_at desc);
create index notifications_unread_idx on public.notifications (recipient_id) where read_at is null;
create index notifications_actor_idx on public.notifications (actor_id);
-- One unread message notification per conversation keeps busy chats from flooding the list.
create unique index notifications_unread_message_idx on public.notifications (recipient_id, related_id)
    where kind = 'message' and read_at is null;

create table public.mail_outbox (
    id uuid primary key default gen_random_uuid(),
    notification_id uuid not null unique references public.notifications (id) on delete cascade,
    status text not null default 'pending' check (status in ('pending', 'sending', 'sent', 'failed')),
    attempts integer not null default 0 check (attempts >= 0),
    claimed_at timestamptz,
    sent_at timestamptz,
    created_at timestamptz not null default now()
);
create index mail_outbox_claim_idx on public.mail_outbox (status, created_at);

create function swapfix_private.notify(
    p_recipient uuid, p_kind text, p_title text, p_body text, p_link text, p_related_type text, p_related_id uuid
) returns void
language plpgsql set search_path = '' as $$
declare
    v_actor uuid := auth.uid();
    v_id uuid;
    v_body text := left(coalesce(p_body, ''), 400);
begin
    if p_recipient is null or p_recipient = v_actor then
        return;
    end if;
    if p_kind = 'message' then
        update public.notifications
        set body = v_body, title = p_title, actor_id = v_actor, created_at = now()
        where recipient_id = p_recipient and related_id = p_related_id and kind = 'message' and read_at is null;
        if found then
            return;
        end if;
    end if;
    insert into public.notifications (recipient_id, actor_id, kind, title, body, link_path, related_type, related_id)
    values (p_recipient, v_actor, p_kind, left(p_title, 160), v_body, p_link, p_related_type, p_related_id)
    returning id into v_id;
    if exists (select 1 from public.profile_private where user_id = p_recipient and email_notifications) then
        insert into public.mail_outbox (notification_id) values (v_id);
    end if;
end;
$$;

-- Account API
create function swapfix_private.api_create_profile(p_account_type text, p_display_name text, p_location text default '')
returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_user();
    v_name text := swapfix_private.clean_text(p_display_name, 'Name', 2, 80);
    v_location text := swapfix_private.clean_text(p_location, 'Location', 0, 120);
begin
    if p_account_type is null or p_account_type not in ('customer', 'technician') then
        perform swapfix_private.fail('SF422', 'Choose a customer or technician account.');
    end if;
    insert into public.profiles (id, account_type, display_name)
    values (v_user, p_account_type, v_name)
    on conflict (id) do nothing;
    if not found then
        perform swapfix_private.fail('SF409', 'This account is already set up.');
    end if;
    insert into public.profile_private (user_id, location) values (v_user, v_location);
    if p_account_type = 'technician' then
        insert into public.technician_profiles (user_id, location) values (v_user, v_location);
    end if;
    return jsonb_build_object('id', v_user, 'account_type', p_account_type);
end;
$$;

create function swapfix_private.api_update_profile(
    p_display_name text, p_location text, p_avatar_path text default null,
    p_clear_avatar boolean default false, p_email_notifications boolean default false
) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_name text := swapfix_private.clean_text(p_display_name, 'Name', 2, 80);
    v_location text := swapfix_private.clean_text(p_location, 'Location', 0, 120);
    v_previous text;
    v_next text;
begin
    select avatar_path into v_previous from public.profiles where id = v_user for update;
    v_next := case when p_clear_avatar then null else coalesce(p_avatar_path, v_previous) end;
    if v_next is not null then
        perform swapfix_private.clean_photo_paths(array[v_next], v_user, 1);
    end if;
    update public.profiles set display_name = v_name, avatar_path = v_next where id = v_user;
    update public.profile_private
    set location = v_location, email_notifications = coalesce(p_email_notifications, false)
    where user_id = v_user;
    return jsonb_build_object(
        'avatar_path', v_next,
        'replaced_avatar_path', case when v_previous is distinct from v_next then v_previous end
    );
end;
$$;

create function swapfix_private.api_save_technician_profile(
    p_headline text, p_bio text, p_location text, p_service_area text, p_available_hours text,
    p_specialties text[], p_supported_brands text[], p_supported_models text[]
) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_technician();
    v_specialties text[] := swapfix_private.clean_list(p_specialties, 'Specialties', 20, 60);
begin
    if cardinality(v_specialties) = 0 then
        perform swapfix_private.fail('SF422', 'Add at least one repair specialty.');
    end if;
    insert into public.technician_profiles (
        user_id, headline, bio, location, service_area, available_hours, specialties, supported_brands, supported_models
    ) values (
        v_user,
        swapfix_private.clean_text(p_headline, 'Headline', 0, 120),
        swapfix_private.clean_text(p_bio, 'About', 0, 2000),
        swapfix_private.clean_text(p_location, 'Location', 2, 120),
        swapfix_private.clean_text(p_service_area, 'Service area', 2, 200),
        swapfix_private.clean_text(p_available_hours, 'Available hours', 2, 200),
        v_specialties,
        swapfix_private.clean_list(p_supported_brands, 'Brands', 30, 60),
        swapfix_private.clean_list(p_supported_models, 'Models', 60, 80)
    )
    on conflict (user_id) do update set
        headline = excluded.headline, bio = excluded.bio, location = excluded.location,
        service_area = excluded.service_area, available_hours = excluded.available_hours,
        specialties = excluded.specialties, supported_brands = excluded.supported_brands,
        supported_models = excluded.supported_models;
    return jsonb_build_object('id', v_user);
end;
$$;

-- Notification API
create function swapfix_private.api_mark_notification_read(p_id uuid) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_user();
    v_link text;
begin
    update public.notifications
    set read_at = coalesce(read_at, now())
    where id = p_id and recipient_id = v_user
    returning link_path into v_link;
    if v_link is null then
        perform swapfix_private.fail('SF404', 'Notification not found.');
    end if;
    return jsonb_build_object('link_path', v_link);
end;
$$;

create function swapfix_private.api_mark_all_notifications_read() returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_user();
    v_count integer;
begin
    update public.notifications set read_at = now() where recipient_id = v_user and read_at is null;
    get diagnostics v_count = row_count;
    return jsonb_build_object('updated', v_count);
end;
$$;

-- Email worker API. Execute is granted to service_role only (see 8/8).
create function swapfix_private.api_mail_claim(p_limit integer default 20)
returns table (outbox_id uuid, email text, display_name text, title text, body text, link_path text)
language plpgsql security definer set search_path = '' as $$
begin
    return query
    with claimed as (
        update public.mail_outbox o
        set status = 'sending', attempts = o.attempts + 1, claimed_at = now()
        where o.id in (
            select candidate.id from public.mail_outbox candidate
            where candidate.attempts < 5
              and (candidate.status = 'pending'
                   or (candidate.status = 'sending' and candidate.claimed_at < now() - interval '15 minutes'))
            order by candidate.created_at
            limit least(greatest(coalesce(p_limit, 20), 1), 50)
            for update skip locked
        )
        returning o.id, o.notification_id
    )
    select c.id, u.email::text, p.display_name, n.title, n.body, n.link_path
    from claimed c
    join public.notifications n on n.id = c.notification_id
    join public.profiles p on p.id = n.recipient_id
    join auth.users u on u.id = n.recipient_id
    where u.email is not null;
end;
$$;

create function swapfix_private.api_mail_mark(p_outbox_id uuid, p_sent boolean) returns jsonb
language plpgsql security definer set search_path = '' as $$
begin
    update public.mail_outbox
    set status = case when p_sent then 'sent' when attempts >= 5 then 'failed' else 'pending' end,
        sent_at = case when p_sent then now() end
    where id = p_outbox_id and status = 'sending';
    return jsonb_build_object('updated', found);
end;
$$;
