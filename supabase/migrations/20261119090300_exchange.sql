-- SwapFix 3/8: exchange listings, trade offers and immutable counteroffer history.
--
-- Lifecycle: exchange open -> agreed -> completed. Acceptance (agreed) and physical handover
-- (completed) are separate events. While agreed, other offers stay pending but cannot be
-- acted on; if the agreement is cancelled the exchange reopens and they become actionable.
-- Lock order is always exchange row first, then offer row.

create table public.exchange_listings (
    id uuid primary key default gen_random_uuid(),
    owner_id uuid not null references public.profiles (id),
    title text not null check (char_length(title) between 3 and 150),
    description text not null default '' check (char_length(description) <= 4000),
    category text not null check (category in ('cellphone', 'tablet', 'laptop', 'accessory', 'spare_part')),
    brand text not null check (char_length(brand) between 1 and 60),
    model text not null default '' check (char_length(model) <= 80),
    condition text not null check (condition in ('new', 'like_new', 'good', 'fair', 'for_parts')),
    known_defects text not null default '' check (char_length(known_defects) <= 2000),
    location text not null check (char_length(location) between 2 and 120),
    photo_paths text[] not null default '{}',
    wanted_item text not null check (char_length(wanted_item) between 2 and 200),
    wanted_details text not null default '' check (char_length(wanted_details) <= 2000),
    cash_preference text not null default 'none'
        check (cash_preference in ('none', 'owner_may_add', 'offerer_may_add', 'either')),
    status text not null default 'open' check (status in ('open', 'agreed', 'completed', 'removed')),
    accepted_offer_id uuid,
    completed_at timestamptz,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    search tsvector generated always as (
        to_tsvector('simple'::regconfig, title || ' ' || brand || ' ' || model || ' ' || wanted_item || ' ' || description)
    ) stored,
    constraint exchange_photos_owned check (swapfix_private.valid_photo_paths(photo_paths, owner_id, 8)),
    constraint exchange_agreed_has_offer check ((status in ('agreed', 'completed')) = (accepted_offer_id is not null)),
    constraint exchange_completed_time check ((status = 'completed') = (completed_at is not null))
);
create index exchange_listings_owner_idx on public.exchange_listings (owner_id, created_at desc);
create index exchange_listings_browse_idx on public.exchange_listings (status, created_at desc);
create index exchange_listings_category_idx on public.exchange_listings (status, category, created_at desc);
create index exchange_listings_brand_idx on public.exchange_listings (lower(brand));
create index exchange_listings_search_idx on public.exchange_listings using gin (search);
create index exchange_listings_accepted_offer_idx on public.exchange_listings (accepted_offer_id);
create trigger exchange_listings_touch before update on public.exchange_listings
for each row execute function swapfix_private.touch_updated_at();

create table public.trade_offers (
    id uuid primary key default gen_random_uuid(),
    exchange_id uuid not null references public.exchange_listings (id),
    offerer_id uuid not null references public.profiles (id),
    owner_id uuid not null references public.profiles (id),
    status text not null default 'pending'
        check (status in ('pending', 'accepted', 'declined', 'withdrawn', 'cancelled', 'completed')),
    current_version integer not null default 1 check (current_version >= 1),
    awaiting_user_id uuid references public.profiles (id),
    accepted_version integer,
    decided_at timestamptz,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint trade_offers_not_self check (offerer_id <> owner_id),
    constraint trade_offers_awaiting check ((status = 'pending') = (awaiting_user_id is not null)),
    constraint trade_offers_awaiting_party check (awaiting_user_id is null or awaiting_user_id in (offerer_id, owner_id)),
    constraint trade_offers_accepted_version check ((status in ('accepted', 'completed')) = (accepted_version is not null))
);
-- The database itself guarantees at most one accepted or completed offer per exchange.
create unique index trade_offers_one_accepted_idx on public.trade_offers (exchange_id)
    where status in ('accepted', 'completed');
create unique index trade_offers_one_active_per_user_idx on public.trade_offers (exchange_id, offerer_id)
    where status in ('pending', 'accepted');
create index trade_offers_exchange_idx on public.trade_offers (exchange_id, created_at desc);
create index trade_offers_offerer_idx on public.trade_offers (offerer_id, created_at desc);
create index trade_offers_owner_idx on public.trade_offers (owner_id, created_at desc);
create index trade_offers_awaiting_idx on public.trade_offers (awaiting_user_id) where awaiting_user_id is not null;
create trigger trade_offers_touch before update on public.trade_offers
for each row execute function swapfix_private.touch_updated_at();

alter table public.exchange_listings
    add constraint exchange_listings_accepted_offer_fkey foreign key (accepted_offer_id) references public.trade_offers (id);

create table public.trade_offer_terms (
    id uuid primary key default gen_random_uuid(),
    offer_id uuid not null references public.trade_offers (id) on delete cascade,
    version integer not null check (version >= 1),
    proposed_by uuid not null references public.profiles (id),
    device_title text not null check (char_length(device_title) between 3 and 150),
    brand text not null check (char_length(brand) between 1 and 60),
    model text not null default '' check (char_length(model) <= 80),
    condition text not null check (condition in ('new', 'like_new', 'good', 'fair', 'for_parts')),
    known_defects text not null default '' check (char_length(known_defects) <= 2000),
    message text not null default '' check (char_length(message) <= 2000),
    cash_amount numeric(12, 2) not null default 0 check (cash_amount >= 0),
    cash_payer text not null default 'none' check (cash_payer in ('none', 'owner', 'offerer')),
    photo_owner_id uuid not null references public.profiles (id),
    photo_paths text[] not null default '{}',
    created_at timestamptz not null default now(),
    unique (offer_id, version),
    constraint trade_terms_cash_payer check ((cash_payer = 'none') = (cash_amount = 0)),
    constraint trade_terms_photos_owned check (swapfix_private.valid_photo_paths(photo_paths, photo_owner_id, 6))
);
create index trade_offer_terms_proposed_by_idx on public.trade_offer_terms (proposed_by);
create index trade_offer_terms_photo_owner_idx on public.trade_offer_terms (photo_owner_id);
create index trade_offer_terms_photos_idx on public.trade_offer_terms using gin (photo_paths);
create trigger trade_offer_terms_immutable before update on public.trade_offer_terms
for each row execute function swapfix_private.forbid_update();

create table public.trade_offer_events (
    id uuid primary key default gen_random_uuid(),
    offer_id uuid not null references public.trade_offers (id) on delete cascade,
    actor_id uuid not null references public.profiles (id),
    event text not null check (event in ('offered', 'countered', 'accepted', 'declined', 'withdrawn', 'cancelled', 'completed')),
    version integer not null,
    note text not null default '' check (char_length(note) <= 1000),
    created_at timestamptz not null default now()
);
create index trade_offer_events_offer_idx on public.trade_offer_events (offer_id, created_at);
create index trade_offer_events_actor_idx on public.trade_offer_events (actor_id);
create trigger trade_offer_events_immutable before update on public.trade_offer_events
for each row execute function swapfix_private.forbid_update();

create function swapfix_private.check_cash(p_amount numeric, p_payer text) returns numeric
language plpgsql set search_path = '' as $$
declare
    v_amount numeric := swapfix_private.clean_money(coalesce(p_amount, 0), 'Cash amount', 0);
begin
    if p_payer is null or p_payer not in ('none', 'owner', 'offerer') then
        perform swapfix_private.fail('SF422', 'Choose who pays the cash amount.');
    end if;
    if (p_payer = 'none') <> (v_amount = 0) then
        perform swapfix_private.fail('SF422', 'Enter a cash amount together with who pays it.');
    end if;
    return v_amount;
end;
$$;

create function swapfix_private.check_condition(p_condition text) returns void
language plpgsql set search_path = '' as $$
begin
    if p_condition is null or p_condition not in ('new', 'like_new', 'good', 'fair', 'for_parts') then
        perform swapfix_private.fail('SF422', 'Choose a valid condition.');
    end if;
end;
$$;

-- Cancels other live offers when a listing leaves the market.
create function swapfix_private.close_pending_offers(p_exchange public.exchange_listings, p_actor uuid, p_note text) returns void
language plpgsql set search_path = '' as $$
declare
    v_offer public.trade_offers;
begin
    for v_offer in
        select * from public.trade_offers where exchange_id = p_exchange.id and status = 'pending' for update
    loop
        update public.trade_offers
        set status = 'cancelled', awaiting_user_id = null, decided_at = now()
        where id = v_offer.id;
        insert into public.trade_offer_events (offer_id, actor_id, event, version, note)
        values (v_offer.id, p_actor, 'cancelled', v_offer.current_version, p_note);
        perform swapfix_private.notify(
            v_offer.offerer_id, 'trade_update', 'Exchange closed', p_exchange.title || ': ' || p_note,
            '/offers/' || v_offer.id, 'offer', v_offer.id
        );
    end loop;
end;
$$;

create function swapfix_private.api_save_exchange(
    p_id uuid, p_title text, p_description text, p_category text, p_brand text, p_model text, p_condition text,
    p_known_defects text, p_location text, p_photo_paths text[], p_wanted_item text, p_wanted_details text,
    p_cash_preference text
) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_title text := swapfix_private.clean_text(p_title, 'Title', 3, 150);
    v_description text := swapfix_private.clean_text(p_description, 'Description', 0, 4000);
    v_brand text := swapfix_private.clean_text(p_brand, 'Brand', 1, 60);
    v_model text := swapfix_private.clean_text(p_model, 'Model', 0, 80);
    v_defects text := swapfix_private.clean_text(p_known_defects, 'Known defects', 0, 2000);
    v_location text := swapfix_private.clean_text(p_location, 'Location', 2, 120);
    v_wanted text := swapfix_private.clean_text(p_wanted_item, 'Wanted gadget', 2, 200);
    v_wanted_details text := swapfix_private.clean_text(p_wanted_details, 'Wanted details', 0, 2000);
    v_photos text[] := swapfix_private.clean_photo_paths(p_photo_paths, v_user, 8);
    v_current public.exchange_listings;
    v_id uuid;
begin
    perform swapfix_private.check_condition(p_condition);
    if p_category is null or p_category not in ('cellphone', 'tablet', 'laptop', 'accessory', 'spare_part') then
        perform swapfix_private.fail('SF422', 'Choose a valid category.');
    end if;
    if p_cash_preference is null or p_cash_preference not in ('none', 'owner_may_add', 'offerer_may_add', 'either') then
        perform swapfix_private.fail('SF422', 'Choose a cash preference.');
    end if;
    if p_id is null then
        insert into public.exchange_listings (
            owner_id, title, description, category, brand, model, condition, known_defects, location,
            photo_paths, wanted_item, wanted_details, cash_preference
        ) values (
            v_user, v_title, v_description, p_category, v_brand, v_model, p_condition, v_defects, v_location,
            v_photos, v_wanted, v_wanted_details, p_cash_preference
        ) returning id into v_id;
        return jsonb_build_object('id', v_id, 'removed_paths', '[]'::jsonb);
    end if;
    select * into v_current from public.exchange_listings where id = p_id for update;
    if not found or v_current.owner_id <> v_user then
        perform swapfix_private.fail('SF404', 'Exchange listing not found.');
    end if;
    if v_current.status <> 'open' then
        perform swapfix_private.fail('SF409', 'Only open exchange listings can be edited.');
    end if;
    update public.exchange_listings set
        title = v_title, description = v_description, category = p_category, brand = v_brand, model = v_model,
        condition = p_condition, known_defects = v_defects, location = v_location, photo_paths = v_photos,
        wanted_item = v_wanted, wanted_details = v_wanted_details, cash_preference = p_cash_preference
    where id = p_id;
    return jsonb_build_object('id', p_id, 'removed_paths', (
        select coalesce(jsonb_agg(path), '[]'::jsonb) from unnest(v_current.photo_paths) as path where path <> all (v_photos)
    ));
end;
$$;

create function swapfix_private.api_remove_exchange(p_id uuid) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_exchange public.exchange_listings;
begin
    select * into v_exchange from public.exchange_listings where id = p_id for update;
    if not found or v_exchange.owner_id <> v_user then
        perform swapfix_private.fail('SF404', 'Exchange listing not found.');
    end if;
    if v_exchange.status <> 'open' then
        perform swapfix_private.fail('SF409', 'Cancel the agreement before removing this listing.');
    end if;
    perform swapfix_private.close_pending_offers(v_exchange, v_user, 'The owner removed this listing.');
    update public.exchange_listings set status = 'removed' where id = p_id;
    return jsonb_build_object('id', p_id, 'status', 'removed');
end;
$$;

create function swapfix_private.api_submit_offer(
    p_exchange_id uuid, p_device_title text, p_brand text, p_model text, p_condition text,
    p_known_defects text, p_message text, p_cash_amount numeric, p_cash_payer text, p_photo_paths text[]
) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_title text := swapfix_private.clean_text(p_device_title, 'Gadget name', 3, 150);
    v_brand text := swapfix_private.clean_text(p_brand, 'Brand', 1, 60);
    v_model text := swapfix_private.clean_text(p_model, 'Model', 0, 80);
    v_defects text := swapfix_private.clean_text(p_known_defects, 'Known defects', 0, 2000);
    v_message text := swapfix_private.clean_text(p_message, 'Message', 1, 2000);
    v_cash numeric := swapfix_private.check_cash(p_cash_amount, p_cash_payer);
    v_photos text[] := swapfix_private.clean_photo_paths(p_photo_paths, v_user, 6);
    v_exchange public.exchange_listings;
    v_offer uuid;
    v_conversation uuid;
begin
    perform swapfix_private.check_condition(p_condition);
    select * into v_exchange from public.exchange_listings where id = p_exchange_id for update;
    if not found or v_exchange.status = 'removed' then
        perform swapfix_private.fail('SF404', 'Exchange listing not found.');
    end if;
    if v_exchange.owner_id = v_user then
        perform swapfix_private.fail('SF403', 'You cannot send an offer to your own listing.');
    end if;
    if v_exchange.status <> 'open' then
        perform swapfix_private.fail('SF409', 'This exchange is no longer taking offers.');
    end if;
    if exists (
        select 1 from public.trade_offers
        where exchange_id = p_exchange_id and offerer_id = v_user and status in ('pending', 'accepted')
    ) then
        perform swapfix_private.fail('SF409', 'You already have an active offer on this listing.');
    end if;
    insert into public.trade_offers (exchange_id, offerer_id, owner_id, awaiting_user_id)
    values (p_exchange_id, v_user, v_exchange.owner_id, v_exchange.owner_id)
    returning id into v_offer;
    insert into public.trade_offer_terms (
        offer_id, version, proposed_by, device_title, brand, model, condition, known_defects, message,
        cash_amount, cash_payer, photo_owner_id, photo_paths
    ) values (
        v_offer, 1, v_user, v_title, v_brand, v_model, p_condition, v_defects, v_message,
        v_cash, p_cash_payer, v_user, v_photos
    );
    insert into public.trade_offer_events (offer_id, actor_id, event, version) values (v_offer, v_user, 'offered', 1);
    v_conversation := swapfix_private.ensure_conversation(
        'offer', v_offer, v_user, v_exchange.owner_id, 'Offer: ' || v_exchange.title
    );
    perform swapfix_private.post_message(v_conversation, v_user, v_message, false);
    perform swapfix_private.notify(
        v_exchange.owner_id, 'trade_offer', 'New trade offer', v_title || ' for ' || v_exchange.title,
        '/offers/' || v_offer, 'offer', v_offer
    );
    return jsonb_build_object('id', v_offer, 'conversation_id', v_conversation);
end;
$$;

-- Loads and locks an offer that the caller must answer, rejecting stale or conflicting decisions.
create function swapfix_private.lock_offer_for_response(
    p_offer_id uuid, p_user uuid, p_expected_version integer,
    out r_offer public.trade_offers, out r_exchange public.exchange_listings
)
language plpgsql set search_path = '' as $$
declare
    v_exchange_id uuid;
begin
    select exchange_id into v_exchange_id from public.trade_offers
    where id = p_offer_id and p_user in (offerer_id, owner_id);
    if v_exchange_id is null then
        perform swapfix_private.fail('SF404', 'Offer not found.');
    end if;
    select * into r_exchange from public.exchange_listings where id = v_exchange_id for update;
    select * into r_offer from public.trade_offers where id = p_offer_id for update;
    if r_offer.status <> 'pending' then
        perform swapfix_private.fail('SF409', 'This offer has already been decided.');
    end if;
    if p_expected_version is null or p_expected_version < 1 or r_offer.current_version is distinct from p_expected_version then
        perform swapfix_private.fail('SF409', 'These terms changed. Review the latest version first.');
    end if;
    if r_offer.awaiting_user_id <> p_user then
        perform swapfix_private.fail('SF403', 'It is the other party''s turn to respond.');
    end if;
    if r_exchange.status <> 'open' then
        perform swapfix_private.fail('SF409', 'This exchange is not open for decisions.');
    end if;
end;
$$;

create function swapfix_private.api_counter_offer(
    p_offer_id uuid, p_expected_version integer, p_device_title text, p_brand text, p_model text,
    p_condition text, p_known_defects text, p_message text, p_cash_amount numeric, p_cash_payer text,
    p_photo_paths text[] default null
) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_title text := swapfix_private.clean_text(p_device_title, 'Gadget name', 3, 150);
    v_brand text := swapfix_private.clean_text(p_brand, 'Brand', 1, 60);
    v_model text := swapfix_private.clean_text(p_model, 'Model', 0, 80);
    v_defects text := swapfix_private.clean_text(p_known_defects, 'Known defects', 0, 2000);
    v_message text := swapfix_private.clean_text(p_message, 'Message', 1, 2000);
    v_cash numeric := swapfix_private.check_cash(p_cash_amount, p_cash_payer);
    v_locked record;
    v_offer public.trade_offers;
    v_exchange public.exchange_listings;
    v_previous public.trade_offer_terms;
    v_photos text[];
    v_next integer;
    v_other uuid;
begin
    perform swapfix_private.check_condition(p_condition);
    select * into v_locked from swapfix_private.lock_offer_for_response(p_offer_id, v_user, p_expected_version);
    v_offer := v_locked.r_offer;
    v_exchange := v_locked.r_exchange;
    select * into v_previous from public.trade_offer_terms
    where offer_id = p_offer_id and version = v_offer.current_version;
    -- Photos show the offerer's gadget, so only the offerer can replace them.
    if p_photo_paths is not null and v_user = v_offer.offerer_id then
        v_photos := swapfix_private.clean_photo_paths(p_photo_paths, v_offer.offerer_id, 6);
    else
        v_photos := v_previous.photo_paths;
    end if;
    v_next := v_offer.current_version + 1;
    v_other := case when v_user = v_offer.owner_id then v_offer.offerer_id else v_offer.owner_id end;
    insert into public.trade_offer_terms (
        offer_id, version, proposed_by, device_title, brand, model, condition, known_defects, message,
        cash_amount, cash_payer, photo_owner_id, photo_paths
    ) values (
        p_offer_id, v_next, v_user, v_title, v_brand, v_model, p_condition, v_defects, v_message,
        v_cash, p_cash_payer, v_offer.offerer_id, v_photos
    );
    update public.trade_offers set current_version = v_next, awaiting_user_id = v_other where id = p_offer_id;
    insert into public.trade_offer_events (offer_id, actor_id, event, version) values (p_offer_id, v_user, 'countered', v_next);
    perform swapfix_private.notify(
        v_other, 'trade_counter', 'New counteroffer', v_exchange.title || ': your response is needed',
        '/offers/' || p_offer_id, 'offer', p_offer_id
    );
    return jsonb_build_object('id', p_offer_id, 'version', v_next, 'awaiting_user_id', v_other);
end;
$$;

create function swapfix_private.api_accept_offer(p_offer_id uuid, p_expected_version integer) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_locked record;
    v_offer public.trade_offers;
    v_exchange public.exchange_listings;
    v_other uuid;
begin
    select * into v_locked from swapfix_private.lock_offer_for_response(p_offer_id, v_user, p_expected_version);
    v_offer := v_locked.r_offer;
    v_exchange := v_locked.r_exchange;
    update public.trade_offers
    set status = 'accepted', accepted_version = current_version, awaiting_user_id = null, decided_at = now()
    where id = p_offer_id;
    update public.exchange_listings set status = 'agreed', accepted_offer_id = p_offer_id where id = v_exchange.id;
    insert into public.trade_offer_events (offer_id, actor_id, event, version)
    values (p_offer_id, v_user, 'accepted', v_offer.current_version);
    v_other := case when v_user = v_offer.owner_id then v_offer.offerer_id else v_offer.owner_id end;
    perform swapfix_private.notify(
        v_other, 'trade_accepted', 'Trade offer accepted', v_exchange.title || ': arrange the handover in chat',
        '/offers/' || p_offer_id, 'offer', p_offer_id
    );
    return jsonb_build_object('id', p_offer_id, 'status', 'accepted', 'exchange_status', 'agreed');
end;
$$;

create function swapfix_private.api_decline_offer(p_offer_id uuid, p_expected_version integer, p_note text default '') returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_note text := swapfix_private.clean_text(p_note, 'Note', 0, 1000);
    v_locked record;
    v_offer public.trade_offers;
    v_other uuid;
begin
    select * into v_locked from swapfix_private.lock_offer_for_response(p_offer_id, v_user, p_expected_version);
    v_offer := v_locked.r_offer;
    update public.trade_offers set status = 'declined', awaiting_user_id = null, decided_at = now() where id = p_offer_id;
    insert into public.trade_offer_events (offer_id, actor_id, event, version, note)
    values (p_offer_id, v_user, 'declined', v_offer.current_version, v_note);
    v_other := case when v_user = v_offer.owner_id then v_offer.offerer_id else v_offer.owner_id end;
    perform swapfix_private.notify(
        v_other, 'trade_declined', 'Trade offer declined', (v_locked.r_exchange).title,
        '/offers/' || p_offer_id, 'offer', p_offer_id
    );
    return jsonb_build_object('id', p_offer_id, 'status', 'declined');
end;
$$;

create function swapfix_private.api_withdraw_offer(p_offer_id uuid) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_offer public.trade_offers;
    v_title text;
begin
    select * into v_offer from public.trade_offers where id = p_offer_id and offerer_id = v_user for update;
    if not found then
        perform swapfix_private.fail('SF404', 'Offer not found.');
    end if;
    if v_offer.status <> 'pending' then
        perform swapfix_private.fail('SF409', 'Only pending offers can be withdrawn.');
    end if;
    update public.trade_offers set status = 'withdrawn', awaiting_user_id = null, decided_at = now() where id = p_offer_id;
    insert into public.trade_offer_events (offer_id, actor_id, event, version)
    values (p_offer_id, v_user, 'withdrawn', v_offer.current_version);
    select title into v_title from public.exchange_listings where id = v_offer.exchange_id;
    perform swapfix_private.notify(
        v_offer.owner_id, 'trade_update', 'Offer withdrawn', v_title, '/offers/' || p_offer_id, 'offer', p_offer_id
    );
    return jsonb_build_object('id', p_offer_id, 'status', 'withdrawn');
end;
$$;

create function swapfix_private.api_cancel_agreement(p_exchange_id uuid, p_note text) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_note text := swapfix_private.clean_text(p_note, 'Reason', 2, 1000);
    v_exchange public.exchange_listings;
    v_offer public.trade_offers;
    v_other uuid;
begin
    select * into v_exchange from public.exchange_listings where id = p_exchange_id for update;
    if not found then
        perform swapfix_private.fail('SF404', 'Exchange listing not found.');
    end if;
    if v_exchange.status <> 'agreed' then
        perform swapfix_private.fail('SF409', 'This exchange has no active agreement.');
    end if;
    select * into v_offer from public.trade_offers where id = v_exchange.accepted_offer_id for update;
    if v_user not in (v_offer.owner_id, v_offer.offerer_id) then
        perform swapfix_private.fail('SF404', 'Exchange listing not found.');
    end if;
    update public.exchange_listings set status = 'open', accepted_offer_id = null where id = p_exchange_id;
    update public.trade_offers set status = 'cancelled', accepted_version = null, decided_at = now() where id = v_offer.id;
    insert into public.trade_offer_events (offer_id, actor_id, event, version, note)
    values (v_offer.id, v_user, 'cancelled', v_offer.current_version, v_note);
    v_other := case when v_user = v_offer.owner_id then v_offer.offerer_id else v_offer.owner_id end;
    perform swapfix_private.notify(
        v_other, 'trade_update', 'Exchange agreement cancelled', v_exchange.title || ': ' || v_note,
        '/offers/' || v_offer.id, 'offer', v_offer.id
    );
    return jsonb_build_object('id', p_exchange_id, 'status', 'open');
end;
$$;

create function swapfix_private.api_complete_exchange(p_exchange_id uuid) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_exchange public.exchange_listings;
    v_offer public.trade_offers;
begin
    select * into v_exchange from public.exchange_listings where id = p_exchange_id for update;
    if not found or v_exchange.owner_id <> v_user then
        perform swapfix_private.fail('SF404', 'Exchange listing not found.');
    end if;
    if v_exchange.status <> 'agreed' then
        perform swapfix_private.fail('SF409', 'Accept an offer before completing the exchange.');
    end if;
    select * into v_offer from public.trade_offers where id = v_exchange.accepted_offer_id for update;
    update public.trade_offers set status = 'completed', decided_at = now() where id = v_offer.id;
    update public.exchange_listings set status = 'completed', completed_at = now() where id = p_exchange_id;
    insert into public.trade_offer_events (offer_id, actor_id, event, version)
    values (v_offer.id, v_user, 'completed', v_offer.current_version);
    perform swapfix_private.close_pending_offers(v_exchange, v_user, 'The exchange was completed with another offer.');
    perform swapfix_private.notify(
        v_offer.offerer_id, 'trade_update', 'Exchange completed', v_exchange.title,
        '/offers/' || v_offer.id, 'offer', v_offer.id
    );
    return jsonb_build_object('id', p_exchange_id, 'status', 'completed');
end;
$$;

create function public.sf_search_exchanges(
    p_query text default null, p_category text default null, p_brand text default null,
    p_condition text default null, p_location text default null, p_limit integer default 24, p_offset integer default 0
) returns jsonb
language sql stable security invoker set search_path = '' as $$
    with filtered as (
        select e.* from public.exchange_listings e
        where e.status in ('open', 'agreed')
          and (nullif(btrim(p_query), '') is null or e.search @@ swapfix_private.prefix_tsquery(p_query))
          and (nullif(p_category, '') is null or e.category = p_category)
          and (nullif(btrim(p_brand), '') is null or lower(e.brand) = lower(btrim(p_brand)))
          and (nullif(p_condition, '') is null or e.condition = p_condition)
          and (nullif(btrim(p_location), '') is null or e.location ilike swapfix_private.like_pattern(p_location))
    ), page as (
        select f.*, row_number() over (order by (f.status = 'open') desc, f.created_at desc, f.id) as position
        from filtered f
        order by position
        limit least(greatest(coalesce(p_limit, 24), 1), 48)
        offset least(greatest(coalesce(p_offset, 0), 0), 10000)
    )
    select jsonb_build_object(
        'total', (select count(*) from filtered),
        'items', coalesce((
            select jsonb_agg(jsonb_build_object(
                'id', p.id, 'title', p.title, 'category', p.category, 'brand', p.brand, 'model', p.model,
                'condition', p.condition, 'known_defects', p.known_defects, 'location', p.location,
                'photo_paths', p.photo_paths, 'wanted_item', p.wanted_item, 'cash_preference', p.cash_preference,
                'status', p.status, 'created_at', p.created_at,
                'owner', jsonb_build_object('id', o.id, 'display_name', o.display_name, 'avatar_path', o.avatar_path)
            ) order by p.position)
            from page p join public.profiles o on o.id = p.owner_id
        ), '[]'::jsonb)
    );
$$;
