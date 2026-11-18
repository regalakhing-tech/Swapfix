-- SwapFix 4/8: repair services, requests, quotations, added costs and history.
--
-- Main flow: request_sent -> quotation_sent -> accepted -> in_repair -> ready_for_pickup -> completed.
-- Decisions the README left open:
--   * Declining a quotation returns the job to request_sent. The declined quotation is kept and
--     the technician may send a new version, or either party may cancel.
--   * A technician may replace a still-pending quotation; the old version becomes superseded.
--   * After acceptance the quotation is frozen. Price changes happen only through added costs.
--   * The customer may cancel before work starts. The technician may cancel until the device
--     is ready for pickup, with a reason. Cancelled jobs keep their full history.
--   * A pending added cost blocks moving to ready_for_pickup. A declined one leaves the total alone.

create table public.repair_services (
    id uuid primary key default gen_random_uuid(),
    technician_id uuid not null references public.technician_profiles (user_id),
    name text not null check (char_length(name) between 3 and 120),
    description text not null default '' check (char_length(description) <= 2000),
    supported_brands text[] not null default '{}',
    supported_models text[] not null default '{}',
    estimated_price numeric(12, 2) not null check (estimated_price >= 0),
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);
create index repair_services_technician_idx on public.repair_services (technician_id, is_active, created_at desc);
create index repair_services_active_idx on public.repair_services (created_at desc) where is_active;
create trigger repair_services_touch before update on public.repair_services
for each row execute function swapfix_private.touch_updated_at();

create table public.repair_requests (
    id uuid primary key default gen_random_uuid(),
    customer_id uuid not null references public.profiles (id),
    technician_id uuid not null references public.technician_profiles (user_id),
    service_id uuid references public.repair_services (id),
    requested_service text not null default '' check (char_length(requested_service) <= 120),
    device_type text not null check (device_type in ('cellphone', 'tablet', 'laptop', 'other')),
    device_brand text not null check (char_length(device_brand) between 1 and 60),
    device_model text not null check (char_length(device_model) between 1 and 80),
    problem text not null check (char_length(problem) between 5 and 4000),
    photo_paths text[] not null default '{}',
    status text not null default 'request_sent' check (status in (
        'request_sent', 'quotation_sent', 'accepted', 'in_repair', 'ready_for_pickup', 'completed', 'cancelled'
    )),
    accepted_quotation_id uuid,
    approved_total numeric(12, 2) check (approved_total >= 0),
    estimated_completion date,
    status_changed_at timestamptz not null default now(),
    completed_at timestamptz,
    cancelled_at timestamptz,
    cancelled_by uuid references public.profiles (id),
    cancel_reason text not null default '' check (char_length(cancel_reason) <= 1000),
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint repair_not_self check (customer_id <> technician_id),
    constraint repair_photos_owned check (swapfix_private.valid_photo_paths(photo_paths, customer_id, 6)),
    constraint repair_work_requires_approval check (
        status not in ('accepted', 'in_repair', 'ready_for_pickup', 'completed')
        or (accepted_quotation_id is not null and approved_total is not null)
    ),
    constraint repair_completed_time check ((status = 'completed') = (completed_at is not null)),
    constraint repair_cancelled_time check ((status = 'cancelled') = (cancelled_at is not null))
);
create index repair_requests_customer_idx on public.repair_requests (customer_id, created_at desc);
create index repair_requests_technician_idx on public.repair_requests (technician_id, status, updated_at desc);
create index repair_requests_service_idx on public.repair_requests (service_id);
create index repair_requests_quotation_idx on public.repair_requests (accepted_quotation_id);
create index repair_requests_cancelled_by_idx on public.repair_requests (cancelled_by);
create index repair_requests_photos_idx on public.repair_requests using gin (photo_paths);
create trigger repair_requests_touch before update on public.repair_requests
for each row execute function swapfix_private.touch_updated_at();

create table public.repair_quotations (
    id uuid primary key default gen_random_uuid(),
    repair_id uuid not null references public.repair_requests (id) on delete cascade,
    technician_id uuid not null references public.profiles (id),
    version integer not null check (version >= 1),
    scope text not null check (char_length(scope) between 5 and 4000),
    parts text not null default '' check (char_length(parts) <= 2000),
    cost numeric(12, 2) not null check (cost > 0),
    estimated_completion date not null,
    status text not null default 'pending' check (status in ('pending', 'accepted', 'declined', 'superseded', 'withdrawn')),
    decision_note text not null default '' check (char_length(decision_note) <= 1000),
    decided_at timestamptz,
    created_at timestamptz not null default now(),
    unique (repair_id, version)
);
create unique index repair_quotations_one_pending_idx on public.repair_quotations (repair_id) where status = 'pending';
create unique index repair_quotations_one_accepted_idx on public.repair_quotations (repair_id) where status = 'accepted';
create index repair_quotations_technician_idx on public.repair_quotations (technician_id);

alter table public.repair_requests
    add constraint repair_requests_accepted_quotation_fkey
    foreign key (accepted_quotation_id) references public.repair_quotations (id);

create table public.repair_added_costs (
    id uuid primary key default gen_random_uuid(),
    repair_id uuid not null references public.repair_requests (id) on delete cascade,
    technician_id uuid not null references public.profiles (id),
    reason text not null check (char_length(reason) between 5 and 2000),
    proposed_work text not null check (char_length(proposed_work) between 5 and 2000),
    amount numeric(12, 2) not null check (amount > 0),
    previous_total numeric(12, 2) not null check (previous_total >= 0),
    revised_total numeric(12, 2) not null,
    previous_completion date,
    revised_completion date,
    status text not null default 'pending' check (status in ('pending', 'accepted', 'declined', 'withdrawn')),
    decision_note text not null default '' check (char_length(decision_note) <= 1000),
    decided_at timestamptz,
    created_at timestamptz not null default now(),
    constraint repair_added_cost_total check (revised_total = previous_total + amount)
);
create unique index repair_added_costs_one_pending_idx on public.repair_added_costs (repair_id) where status = 'pending';
create index repair_added_costs_repair_idx on public.repair_added_costs (repair_id, created_at);
create index repair_added_costs_technician_idx on public.repair_added_costs (technician_id);

create table public.repair_events (
    id uuid primary key default gen_random_uuid(),
    repair_id uuid not null references public.repair_requests (id) on delete cascade,
    actor_id uuid references public.profiles (id),
    kind text not null check (kind in (
        'status', 'quotation_sent', 'quotation_accepted', 'quotation_declined', 'quotation_superseded',
        'added_cost_requested', 'added_cost_accepted', 'added_cost_declined', 'added_cost_withdrawn', 'review_submitted'
    )),
    from_status text,
    to_status text,
    amount numeric(12, 2),
    related_id uuid,
    note text not null default '' check (char_length(note) <= 1000),
    created_at timestamptz not null default now()
);
create index repair_events_repair_idx on public.repair_events (repair_id, created_at);
create index repair_events_actor_idx on public.repair_events (actor_id);
create trigger repair_events_immutable before update on public.repair_events
for each row execute function swapfix_private.forbid_update();

-- Guards apply to every writer, including database administrators and future functions.
create function swapfix_private.guard_repair_status() returns trigger
language plpgsql set search_path = '' as $$
begin
    if tg_op = 'INSERT' then
        if new.status <> 'request_sent' then
            raise exception using errcode = 'SF409', message = 'New repairs must start as Request Sent.';
        end if;
        return new;
    end if;
    if new.status = old.status then
        return new;
    end if;
    if not ((old.status, new.status) in (
        ('request_sent', 'quotation_sent'), ('request_sent', 'cancelled'),
        ('quotation_sent', 'accepted'), ('quotation_sent', 'request_sent'), ('quotation_sent', 'cancelled'),
        ('accepted', 'in_repair'), ('accepted', 'cancelled'),
        ('in_repair', 'ready_for_pickup'), ('in_repair', 'cancelled'),
        ('ready_for_pickup', 'completed')
    )) then
        raise exception using errcode = 'SF409', message = 'That repair status change is not allowed.';
    end if;
    new.status_changed_at := now();
    if new.status = 'completed' then
        new.completed_at := now();
    end if;
    return new;
end;
$$;
create trigger repair_requests_guard_status before insert or update of status on public.repair_requests
for each row execute function swapfix_private.guard_repair_status();

create function swapfix_private.record_repair_status() returns trigger
language plpgsql set search_path = '' as $$
begin
    if tg_op = 'UPDATE' and new.status = old.status then
        return null;
    end if;
    insert into public.repair_events (repair_id, actor_id, kind, from_status, to_status, note)
    values (
        new.id, auth.uid(), 'status', case when tg_op = 'UPDATE' then old.status end, new.status,
        coalesce(current_setting('swapfix.status_note', true), '')
    );
    return null;
end;
$$;
create trigger repair_requests_record_status after insert or update of status on public.repair_requests
for each row execute function swapfix_private.record_repair_status();

create function swapfix_private.guard_quotation_terms() returns trigger
language plpgsql set search_path = '' as $$
begin
    if (new.repair_id, new.technician_id, new.version, new.scope, new.parts, new.cost, new.estimated_completion, new.created_at)
       is distinct from
       (old.repair_id, old.technician_id, old.version, old.scope, old.parts, old.cost, old.estimated_completion, old.created_at)
    then
        raise exception using errcode = 'SF409', message = 'Quotation terms cannot be changed. Send a new version.';
    end if;
    if old.status <> 'pending' then
        raise exception using errcode = 'SF409', message = 'This quotation has already been decided.';
    end if;
    return new;
end;
$$;
create trigger repair_quotations_guard before update on public.repair_quotations
for each row execute function swapfix_private.guard_quotation_terms();

create function swapfix_private.guard_added_cost_terms() returns trigger
language plpgsql set search_path = '' as $$
begin
    if (new.repair_id, new.technician_id, new.reason, new.proposed_work, new.amount, new.previous_total,
        new.revised_total, new.previous_completion, new.revised_completion, new.created_at)
       is distinct from
       (old.repair_id, old.technician_id, old.reason, old.proposed_work, old.amount, old.previous_total,
        old.revised_total, old.previous_completion, old.revised_completion, old.created_at)
    then
        raise exception using errcode = 'SF409', message = 'Added-cost terms cannot be changed.';
    end if;
    if old.status <> 'pending' then
        raise exception using errcode = 'SF409', message = 'This added cost has already been decided.';
    end if;
    return new;
end;
$$;
create trigger repair_added_costs_guard before update on public.repair_added_costs
for each row execute function swapfix_private.guard_added_cost_terms();

create function swapfix_private.add_repair_event(
    p_repair uuid, p_actor uuid, p_kind text, p_related uuid, p_amount numeric, p_note text
) returns void
language sql set search_path = '' as $$
    insert into public.repair_events (repair_id, actor_id, kind, related_id, amount, note)
    values (p_repair, p_actor, p_kind, p_related, p_amount, coalesce(p_note, ''));
$$;

create function swapfix_private.status_label(p_status text) returns text
language sql immutable set search_path = '' as $$
    select case p_status
        when 'request_sent' then 'Request Sent' when 'quotation_sent' then 'Quotation Sent'
        when 'accepted' then 'Accepted' when 'in_repair' then 'In Repair'
        when 'ready_for_pickup' then 'Ready for Pickup' when 'completed' then 'Completed'
        when 'cancelled' then 'Cancelled' else p_status end;
$$;

create function swapfix_private.future_date(p_value date, p_label text) returns date
language plpgsql set search_path = '' as $$
begin
    if p_value is null or p_value < current_date or p_value > current_date + 730 then
        perform swapfix_private.fail('SF422', p_label || ' must be a date from today up to two years ahead.');
    end if;
    return p_value;
end;
$$;

-- Services
create function swapfix_private.api_save_repair_service(
    p_id uuid, p_name text, p_description text, p_supported_brands text[], p_supported_models text[], p_estimated_price numeric
) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_technician();
    v_name text := swapfix_private.clean_text(p_name, 'Service name', 3, 120);
    v_description text := swapfix_private.clean_text(p_description, 'Description', 0, 2000);
    v_brands text[] := swapfix_private.clean_list(p_supported_brands, 'Brands', 30, 60);
    v_models text[] := swapfix_private.clean_list(p_supported_models, 'Models', 60, 80);
    v_price numeric := swapfix_private.clean_money(p_estimated_price, 'Estimated price', 0);
    v_id uuid;
begin
    if p_id is null then
        insert into public.repair_services (technician_id, name, description, supported_brands, supported_models, estimated_price)
        values (v_user, v_name, v_description, v_brands, v_models, v_price)
        returning id into v_id;
    else
        update public.repair_services set
            name = v_name, description = v_description, supported_brands = v_brands,
            supported_models = v_models, estimated_price = v_price
        where id = p_id and technician_id = v_user
        returning id into v_id;
        if v_id is null then
            perform swapfix_private.fail('SF404', 'Service not found.');
        end if;
    end if;
    return jsonb_build_object('id', v_id);
end;
$$;

create function swapfix_private.api_set_service_active(p_id uuid, p_active boolean) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_technician();
begin
    update public.repair_services set is_active = coalesce(p_active, false) where id = p_id and technician_id = v_user;
    if not found then
        perform swapfix_private.fail('SF404', 'Service not found.');
    end if;
    return jsonb_build_object('id', p_id, 'is_active', coalesce(p_active, false));
end;
$$;

-- Requests
create function swapfix_private.api_create_repair_request(
    p_technician_id uuid, p_service_id uuid, p_device_type text, p_device_brand text, p_device_model text,
    p_problem text, p_photo_paths text[]
) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_brand text := swapfix_private.clean_text(p_device_brand, 'Device brand', 1, 60);
    v_model text := swapfix_private.clean_text(p_device_model, 'Device model', 1, 80);
    v_problem text := swapfix_private.clean_text(p_problem, 'Problem description', 5, 4000);
    v_photos text[] := swapfix_private.clean_photo_paths(p_photo_paths, v_user, 6);
    v_service public.repair_services;
    v_customer text;
    v_id uuid;
    v_conversation uuid;
begin
    if p_device_type is null or p_device_type not in ('cellphone', 'tablet', 'laptop', 'other') then
        perform swapfix_private.fail('SF422', 'Choose a device type.');
    end if;
    if p_technician_id = v_user then
        perform swapfix_private.fail('SF403', 'You cannot request a repair from yourself.');
    end if;
    if not exists (
        select 1 from public.technician_profiles t
        join public.profiles p on p.id = t.user_id and p.account_type = 'technician'
        where t.user_id = p_technician_id
    ) then
        perform swapfix_private.fail('SF404', 'Technician not found.');
    end if;
    if p_service_id is not null then
        select * into v_service from public.repair_services
        where id = p_service_id and technician_id = p_technician_id and is_active;
        if not found then
            perform swapfix_private.fail('SF422', 'That service is not available from this technician.');
        end if;
    end if;
    insert into public.repair_requests (
        customer_id, technician_id, service_id, requested_service, device_type, device_brand, device_model, problem, photo_paths
    ) values (
        v_user, p_technician_id, p_service_id, coalesce(v_service.name, ''), p_device_type, v_brand, v_model, v_problem, v_photos
    ) returning id into v_id;
    v_conversation := swapfix_private.ensure_conversation(
        'repair', v_id, v_user, p_technician_id, 'Repair: ' || v_brand || ' ' || v_model
    );
    select display_name into v_customer from public.profiles where id = v_user;
    perform swapfix_private.notify(
        p_technician_id, 'repair_request', 'New repair request', v_customer || ': ' || v_brand || ' ' || v_model,
        '/repairs/' || v_id, 'repair', v_id
    );
    return jsonb_build_object('id', v_id, 'conversation_id', v_conversation);
end;
$$;

create function swapfix_private.lock_repair_as_technician(p_repair_id uuid, p_user uuid) returns public.repair_requests
language plpgsql set search_path = '' as $$
declare
    v_repair public.repair_requests;
begin
    select * into v_repair from public.repair_requests where id = p_repair_id for update;
    if not found or v_repair.technician_id <> p_user then
        perform swapfix_private.fail('SF404', 'Repair not found.');
    end if;
    return v_repair;
end;
$$;

create function swapfix_private.lock_repair_as_customer(p_repair_id uuid, p_user uuid) returns public.repair_requests
language plpgsql set search_path = '' as $$
declare
    v_repair public.repair_requests;
begin
    select * into v_repair from public.repair_requests where id = p_repair_id for update;
    if not found or v_repair.customer_id <> p_user then
        perform swapfix_private.fail('SF404', 'Repair not found.');
    end if;
    return v_repair;
end;
$$;

-- Quotations
create function swapfix_private.api_send_quotation(
    p_repair_id uuid, p_scope text, p_parts text, p_cost numeric, p_estimated_completion date
) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_technician();
    v_scope text := swapfix_private.clean_text(p_scope, 'Repair details', 5, 4000);
    v_parts text := swapfix_private.clean_text(p_parts, 'Parts', 0, 2000);
    v_cost numeric := swapfix_private.clean_money(p_cost, 'Repair cost', 0.01);
    v_date date := swapfix_private.future_date(p_estimated_completion, 'Completion date');
    v_repair public.repair_requests := swapfix_private.lock_repair_as_technician(p_repair_id, v_user);
    v_version integer;
    v_superseded uuid;
    v_id uuid;
begin
    if v_repair.status not in ('request_sent', 'quotation_sent') then
        perform swapfix_private.fail('SF409', 'A quotation cannot be sent at this stage.');
    end if;
    update public.repair_quotations set status = 'superseded', decided_at = now()
    where repair_id = p_repair_id and status = 'pending'
    returning id into v_superseded;
    if v_superseded is not null then
        perform swapfix_private.add_repair_event(p_repair_id, v_user, 'quotation_superseded', v_superseded, null, '');
    end if;
    select coalesce(max(version), 0) + 1 into v_version from public.repair_quotations where repair_id = p_repair_id;
    insert into public.repair_quotations (repair_id, technician_id, version, scope, parts, cost, estimated_completion)
    values (p_repair_id, v_user, v_version, v_scope, v_parts, v_cost, v_date)
    returning id into v_id;
    if v_repair.status = 'request_sent' then
        update public.repair_requests set status = 'quotation_sent' where id = p_repair_id;
    end if;
    perform swapfix_private.add_repair_event(p_repair_id, v_user, 'quotation_sent', v_id, v_cost, '');
    perform swapfix_private.notify(
        v_repair.customer_id, 'quotation_sent', 'Repair quotation received',
        v_repair.device_brand || ' ' || v_repair.device_model, '/repairs/' || p_repair_id, 'repair', p_repair_id
    );
    return jsonb_build_object('id', v_id, 'version', v_version);
end;
$$;

create function swapfix_private.api_decide_quotation(p_quotation_id uuid, p_decision text, p_note text default '') returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_note text := swapfix_private.clean_text(p_note, 'Note', 0, 1000);
    v_repair_id uuid;
    v_repair public.repair_requests;
    v_quotation public.repair_quotations;
begin
    if p_decision is null or p_decision not in ('accepted', 'declined') then
        perform swapfix_private.fail('SF422', 'Choose accept or decline.');
    end if;
    select repair_id into v_repair_id from public.repair_quotations where id = p_quotation_id;
    if v_repair_id is null then
        perform swapfix_private.fail('SF404', 'Quotation not found.');
    end if;
    v_repair := swapfix_private.lock_repair_as_customer(v_repair_id, v_user);
    select * into v_quotation from public.repair_quotations where id = p_quotation_id for update;
    if v_quotation.status <> 'pending' or v_repair.status <> 'quotation_sent' then
        perform swapfix_private.fail('SF409', 'This quotation is no longer open. Review the latest version.');
    end if;
    update public.repair_quotations
    set status = p_decision, decision_note = v_note, decided_at = now()
    where id = p_quotation_id;
    perform set_config('swapfix.status_note', v_note, true);
    if p_decision = 'accepted' then
        update public.repair_requests set
            status = 'accepted', accepted_quotation_id = p_quotation_id,
            approved_total = v_quotation.cost, estimated_completion = v_quotation.estimated_completion
        where id = v_repair_id;
    else
        update public.repair_requests set status = 'request_sent' where id = v_repair_id;
    end if;
    perform set_config('swapfix.status_note', '', true);
    perform swapfix_private.add_repair_event(
        v_repair_id, v_user, 'quotation_' || p_decision, p_quotation_id, v_quotation.cost, v_note
    );
    perform swapfix_private.notify(
        v_repair.technician_id,
        case p_decision when 'accepted' then 'quotation_accepted' else 'quotation_declined' end,
        case p_decision when 'accepted' then 'Quotation accepted' else 'Quotation declined' end,
        v_repair.device_brand || ' ' || v_repair.device_model, '/repairs/' || v_repair_id, 'repair', v_repair_id
    );
    return jsonb_build_object('id', p_quotation_id, 'status', p_decision);
end;
$$;

-- Added costs
create function swapfix_private.api_request_added_cost(
    p_repair_id uuid, p_reason text, p_proposed_work text, p_amount numeric, p_revised_completion date default null
) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_technician();
    v_reason text := swapfix_private.clean_text(p_reason, 'Reason', 5, 2000);
    v_work text := swapfix_private.clean_text(p_proposed_work, 'Proposed work', 5, 2000);
    v_amount numeric := swapfix_private.clean_money(p_amount, 'Added amount', 0.01);
    v_repair public.repair_requests := swapfix_private.lock_repair_as_technician(p_repair_id, v_user);
    v_id uuid;
begin
    if p_revised_completion is not null then
        perform swapfix_private.future_date(p_revised_completion, 'Revised completion date');
    end if;
    if v_repair.status not in ('accepted', 'in_repair') then
        perform swapfix_private.fail('SF409', 'Added costs can be requested only on an accepted or active repair.');
    end if;
    if exists (select 1 from public.repair_added_costs where repair_id = p_repair_id and status = 'pending') then
        perform swapfix_private.fail('SF409', 'An added cost is already waiting for the customer.');
    end if;
    if v_repair.approved_total + v_amount >= 10000000000 then
        perform swapfix_private.fail('SF422', 'The revised total is too large.');
    end if;
    insert into public.repair_added_costs (
        repair_id, technician_id, reason, proposed_work, amount, previous_total, revised_total,
        previous_completion, revised_completion
    ) values (
        p_repair_id, v_user, v_reason, v_work, v_amount, v_repair.approved_total, v_repair.approved_total + v_amount,
        v_repair.estimated_completion, p_revised_completion
    ) returning id into v_id;
    perform swapfix_private.add_repair_event(p_repair_id, v_user, 'added_cost_requested', v_id, v_amount, left(v_reason, 1000));
    perform swapfix_private.notify(
        v_repair.customer_id, 'added_cost_requested', 'Added cost needs approval',
        v_repair.device_brand || ' ' || v_repair.device_model, '/repairs/' || p_repair_id, 'repair', p_repair_id
    );
    return jsonb_build_object('id', v_id, 'revised_total', v_repair.approved_total + v_amount);
end;
$$;

create function swapfix_private.api_withdraw_added_cost(p_id uuid) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_technician();
    v_repair_id uuid;
    v_repair public.repair_requests;
    v_cost public.repair_added_costs;
begin
    select repair_id into v_repair_id from public.repair_added_costs where id = p_id;
    if v_repair_id is null then
        perform swapfix_private.fail('SF404', 'Added cost not found.');
    end if;
    v_repair := swapfix_private.lock_repair_as_technician(v_repair_id, v_user);
    select * into v_cost from public.repair_added_costs where id = p_id for update;
    if v_cost.status <> 'pending' then
        perform swapfix_private.fail('SF409', 'This added cost has already been decided.');
    end if;
    update public.repair_added_costs set status = 'withdrawn', decided_at = now() where id = p_id;
    perform swapfix_private.add_repair_event(v_repair_id, v_user, 'added_cost_withdrawn', p_id, v_cost.amount, '');
    perform swapfix_private.notify(
        v_repair.customer_id, 'repair_status', 'Added cost withdrawn',
        v_repair.device_brand || ' ' || v_repair.device_model, '/repairs/' || v_repair_id, 'repair', v_repair_id
    );
    return jsonb_build_object('id', p_id, 'status', 'withdrawn');
end;
$$;

create function swapfix_private.api_decide_added_cost(p_id uuid, p_decision text, p_note text default '') returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_note text := swapfix_private.clean_text(p_note, 'Note', 0, 1000);
    v_repair_id uuid;
    v_repair public.repair_requests;
    v_cost public.repair_added_costs;
begin
    if p_decision is null or p_decision not in ('accepted', 'declined') then
        perform swapfix_private.fail('SF422', 'Choose accept or decline.');
    end if;
    select repair_id into v_repair_id from public.repair_added_costs where id = p_id;
    if v_repair_id is null then
        perform swapfix_private.fail('SF404', 'Added cost not found.');
    end if;
    v_repair := swapfix_private.lock_repair_as_customer(v_repair_id, v_user);
    select * into v_cost from public.repair_added_costs where id = p_id for update;
    if v_cost.status <> 'pending' then
        perform swapfix_private.fail('SF409', 'This added cost has already been decided.');
    end if;
    if v_repair.status not in ('accepted', 'in_repair') or v_repair.approved_total <> v_cost.previous_total then
        perform swapfix_private.fail('SF409', 'This repair changed. Refresh and try again.');
    end if;
    update public.repair_added_costs set status = p_decision, decision_note = v_note, decided_at = now() where id = p_id;
    if p_decision = 'accepted' then
        update public.repair_requests set
            approved_total = v_cost.revised_total,
            estimated_completion = coalesce(v_cost.revised_completion, estimated_completion)
        where id = v_repair_id;
    end if;
    perform swapfix_private.add_repair_event(v_repair_id, v_user, 'added_cost_' || p_decision, p_id, v_cost.amount, v_note);
    perform swapfix_private.notify(
        v_repair.technician_id,
        case p_decision when 'accepted' then 'added_cost_accepted' else 'added_cost_declined' end,
        case p_decision when 'accepted' then 'Added cost approved' else 'Added cost declined' end,
        v_repair.device_brand || ' ' || v_repair.device_model, '/repairs/' || v_repair_id, 'repair', v_repair_id
    );
    return jsonb_build_object('id', p_id, 'status', p_decision);
end;
$$;

-- Progress
create function swapfix_private.api_update_repair_status(
    p_repair_id uuid, p_expected_status text, p_status text, p_note text default ''
) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_technician();
    v_note text := swapfix_private.clean_text(p_note, 'Note', 0, 1000);
    v_repair public.repair_requests := swapfix_private.lock_repair_as_technician(p_repair_id, v_user);
begin
    if v_repair.status is distinct from p_expected_status then
        perform swapfix_private.fail('SF409', 'This repair changed. Refresh and try again.');
    end if;
    if not ((v_repair.status, p_status) in (
        ('accepted', 'in_repair'), ('in_repair', 'ready_for_pickup'), ('ready_for_pickup', 'completed')
    )) then
        perform swapfix_private.fail('SF409', 'That repair status change is not allowed.');
    end if;
    if p_status = 'ready_for_pickup' and exists (
        select 1 from public.repair_added_costs where repair_id = p_repair_id and status = 'pending'
    ) then
        perform swapfix_private.fail('SF409', 'Resolve the pending added cost first.');
    end if;
    perform set_config('swapfix.status_note', v_note, true);
    update public.repair_requests set status = p_status where id = p_repair_id;
    perform set_config('swapfix.status_note', '', true);
    perform swapfix_private.notify(
        v_repair.customer_id, 'repair_status', 'Repair: ' || swapfix_private.status_label(p_status),
        v_repair.device_brand || ' ' || v_repair.device_model, '/repairs/' || p_repair_id, 'repair', p_repair_id
    );
    return jsonb_build_object('id', p_repair_id, 'status', p_status);
end;
$$;

create function swapfix_private.api_cancel_repair(p_repair_id uuid, p_reason text) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_reason text := swapfix_private.clean_text(p_reason, 'Reason', 2, 1000);
    v_repair public.repair_requests;
    v_other uuid;
begin
    select * into v_repair from public.repair_requests where id = p_repair_id for update;
    if not found or v_user not in (v_repair.customer_id, v_repair.technician_id) then
        perform swapfix_private.fail('SF404', 'Repair not found.');
    end if;
    if v_user = v_repair.customer_id and v_repair.status not in ('request_sent', 'quotation_sent', 'accepted') then
        perform swapfix_private.fail('SF409', 'Work has started. Ask the technician to cancel this repair.');
    end if;
    if v_user = v_repair.technician_id and v_repair.status not in ('request_sent', 'quotation_sent', 'accepted', 'in_repair') then
        perform swapfix_private.fail('SF409', 'This repair can no longer be cancelled.');
    end if;
    update public.repair_quotations set status = 'withdrawn', decided_at = now()
    where repair_id = p_repair_id and status = 'pending';
    update public.repair_added_costs set status = 'withdrawn', decided_at = now()
    where repair_id = p_repair_id and status = 'pending';
    perform set_config('swapfix.status_note', v_reason, true);
    update public.repair_requests
    set status = 'cancelled', cancelled_at = now(), cancelled_by = v_user, cancel_reason = v_reason
    where id = p_repair_id;
    perform set_config('swapfix.status_note', '', true);
    v_other := case when v_user = v_repair.customer_id then v_repair.technician_id else v_repair.customer_id end;
    perform swapfix_private.notify(
        v_other, 'repair_status', 'Repair cancelled', v_repair.device_brand || ' ' || v_repair.device_model || ': ' || v_reason,
        '/repairs/' || p_repair_id, 'repair', p_repair_id
    );
    return jsonb_build_object('id', p_repair_id, 'status', 'cancelled');
end;
$$;

-- Technician discovery. SECURITY INVOKER: only public rows are visible.
create function public.sf_search_technicians(
    p_query text default null, p_area text default null, p_specialty text default null, p_brand text default null,
    p_limit integer default 24, p_offset integer default 0
) returns jsonb
language sql stable security invoker set search_path = '' as $$
    with filtered as (
        select t.*, p.display_name, p.avatar_path
        from public.technician_profiles t
        join public.profiles p on p.id = t.user_id and p.account_type = 'technician'
        where cardinality(t.specialties) > 0
          and (nullif(btrim(p_area), '') is null
               or t.location ilike swapfix_private.like_pattern(p_area)
               or t.service_area ilike swapfix_private.like_pattern(p_area))
          and (nullif(btrim(p_specialty), '') is null
               or exists (select 1 from unnest(t.specialties) s where s ilike swapfix_private.like_pattern(p_specialty))
               or exists (select 1 from public.repair_services r
                          where r.technician_id = t.user_id and r.is_active
                            and r.name ilike swapfix_private.like_pattern(p_specialty)))
          and (nullif(btrim(p_brand), '') is null
               or exists (select 1 from unnest(t.supported_brands) b where lower(b) = lower(btrim(p_brand)))
               or exists (select 1 from public.repair_services r, unnest(r.supported_brands) b
                          where r.technician_id = t.user_id and r.is_active and lower(b) = lower(btrim(p_brand))))
          and (nullif(btrim(p_query), '') is null
               or p.display_name ilike swapfix_private.like_pattern(p_query)
               or t.headline ilike swapfix_private.like_pattern(p_query)
               or exists (select 1 from unnest(t.specialties) s where s ilike swapfix_private.like_pattern(p_query)))
    ), page as (
        select f.*, row_number() over (order by f.rating_average desc, f.rating_count desc, f.created_at desc, f.user_id) as position
        from filtered f
        order by position
        limit least(greatest(coalesce(p_limit, 24), 1), 48)
        offset least(greatest(coalesce(p_offset, 0), 0), 10000)
    )
    select jsonb_build_object(
        'total', (select count(*) from filtered),
        'items', coalesce((
            select jsonb_agg(jsonb_build_object(
                'id', p.user_id, 'display_name', p.display_name, 'avatar_path', p.avatar_path, 'headline', p.headline,
                'location', p.location, 'service_area', p.service_area, 'available_hours', p.available_hours,
                'specialties', p.specialties, 'supported_brands', p.supported_brands,
                'rating_average', p.rating_average, 'rating_count', p.rating_count,
                'starting_price', (select min(r.estimated_price) from public.repair_services r
                                   where r.technician_id = p.user_id and r.is_active)
            ) order by p.position)
            from page p
        ), '[]'::jsonb)
    );
$$;
