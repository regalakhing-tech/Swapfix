-- SwapFix 8/8: RLS policies, explicit grants and the generated public RPC wrappers.
-- Only SwapFix objects are touched here, so unrelated tables in the project stay as they are.

do $$
declare
    v_table text;
begin
    foreach v_table in array array[
        'profiles', 'profile_private', 'technician_profiles', 'notifications', 'mail_outbox',
        'product_listings', 'exchange_listings', 'trade_offers', 'trade_offer_terms', 'trade_offer_events',
        'repair_services', 'repair_requests', 'repair_quotations', 'repair_added_costs', 'repair_events',
        'conversations', 'messages', 'reviews'
    ] loop
        execute format('alter table public.%I enable row level security', v_table);
        -- Hosted projects grant new tables to the API roles by default; start from nothing.
        execute format('revoke all on public.%I from public, anon, authenticated, service_role', v_table);
    end loop;
end;
$$;

grant select on public.profiles, public.technician_profiles, public.repair_services,
    public.product_listings, public.exchange_listings to anon, authenticated;
grant select on public.profile_private, public.notifications, public.trade_offers, public.trade_offer_terms,
    public.trade_offer_events, public.repair_requests, public.repair_quotations, public.repair_added_costs,
    public.repair_events, public.conversations, public.messages, public.reviews to authenticated;
-- public.mail_outbox has no grants and no policies: it is reachable only by the worker functions.

create policy profiles_public_read on public.profiles for select to anon, authenticated using (true);
create policy profile_private_own on public.profile_private for select to authenticated
    using (user_id = (select auth.uid()));
create policy technician_profiles_public_read on public.technician_profiles for select to anon, authenticated using (true);
create policy notifications_own on public.notifications for select to authenticated
    using (recipient_id = (select auth.uid()));

create policy listings_public_read on public.product_listings for select to anon, authenticated
    using (status in ('available', 'reserved'));
create policy listings_owner_read on public.product_listings for select to authenticated
    using (owner_id = (select auth.uid()));
create policy listings_inquirer_read on public.product_listings for select to authenticated
    using (exists (
        select 1 from public.conversations c
        where c.listing_id = product_listings.id and c.initiator_id = (select auth.uid())
    ));

create policy exchanges_public_read on public.exchange_listings for select to anon, authenticated
    using (status in ('open', 'agreed'));
create policy exchanges_owner_read on public.exchange_listings for select to authenticated
    using (owner_id = (select auth.uid()));
create policy exchanges_offerer_read on public.exchange_listings for select to authenticated
    using (exists (
        select 1 from public.trade_offers o
        where o.exchange_id = exchange_listings.id and o.offerer_id = (select auth.uid())
    ));

create policy offers_participant_read on public.trade_offers for select to authenticated
    using ((select auth.uid()) in (offerer_id, owner_id));
create policy offer_terms_participant_read on public.trade_offer_terms for select to authenticated
    using (exists (
        select 1 from public.trade_offers o
        where o.id = trade_offer_terms.offer_id and (select auth.uid()) in (o.offerer_id, o.owner_id)
    ));
create policy offer_events_participant_read on public.trade_offer_events for select to authenticated
    using (exists (
        select 1 from public.trade_offers o
        where o.id = trade_offer_events.offer_id and (select auth.uid()) in (o.offerer_id, o.owner_id)
    ));

create policy services_public_read on public.repair_services for select to anon, authenticated using (is_active);
create policy services_owner_read on public.repair_services for select to authenticated
    using (technician_id = (select auth.uid()));

create policy repairs_participant_read on public.repair_requests for select to authenticated
    using ((select auth.uid()) in (customer_id, technician_id));
create policy quotations_participant_read on public.repair_quotations for select to authenticated
    using (exists (
        select 1 from public.repair_requests r
        where r.id = repair_quotations.repair_id and (select auth.uid()) in (r.customer_id, r.technician_id)
    ));
create policy added_costs_participant_read on public.repair_added_costs for select to authenticated
    using (exists (
        select 1 from public.repair_requests r
        where r.id = repair_added_costs.repair_id and (select auth.uid()) in (r.customer_id, r.technician_id)
    ));
create policy repair_events_participant_read on public.repair_events for select to authenticated
    using (exists (
        select 1 from public.repair_requests r
        where r.id = repair_events.repair_id and (select auth.uid()) in (r.customer_id, r.technician_id)
    ));

create policy conversations_participant_read on public.conversations for select to authenticated
    using ((select auth.uid()) in (initiator_id, recipient_id));
create policy messages_participant_read on public.messages for select to authenticated
    using (exists (
        select 1 from public.conversations c
        where c.id = messages.conversation_id and (select auth.uid()) in (c.initiator_id, c.recipient_id)
    ));

-- Public profiles read reviews through sf_public_reviews, which omits private identifiers.
create policy reviews_participant_read on public.reviews for select to authenticated
    using ((select auth.uid()) in (customer_id, technician_id));

-- Generate one SECURITY INVOKER public.sf_<name> wrapper for each private api_<name> function.
do $$
declare
    v_function record;
    v_arguments text;
begin
    for v_function in
        select p.oid, p.proname, p.pronargs, p.proargnames, p.provolatile
        from pg_catalog.pg_proc p
        join pg_catalog.pg_namespace n on n.oid = p.pronamespace
        where n.nspname = 'swapfix_private' and p.proname like 'api\_%'
    loop
        select coalesce(string_agg(format('%1$I => %1$I', name), ', ' order by position), '') into v_arguments
        from unnest(v_function.proargnames[1:v_function.pronargs]) with ordinality as a(name, position);
        execute format(
            'create function public.%I(%s) returns %s language sql %s security invoker set search_path = '''' as %L',
            'sf_' || substr(v_function.proname, 5),
            pg_catalog.pg_get_function_arguments(v_function.oid),
            pg_catalog.pg_get_function_result(v_function.oid),
            case v_function.provolatile when 's' then 'stable' else 'volatile' end,
            format('select * from swapfix_private.%I(%s)', v_function.proname, v_arguments)
        );
    end loop;
end;
$$;

-- Execute privileges: nothing by default, then the narrow audiences below.
do $$
declare
    v_function record;
    v_audience text;
begin
    for v_function in
        select p.oid::regprocedure as signature, n.nspname, p.proname
        from pg_catalog.pg_proc p
        join pg_catalog.pg_namespace n on n.oid = p.pronamespace
        where n.nspname = 'swapfix_private' or (n.nspname = 'public' and p.proname like 'sf\_%')
    loop
        execute format('revoke all on function %s from public, anon, authenticated, service_role', v_function.signature);
        v_audience := case
            when v_function.proname in ('like_pattern', 'prefix_tsquery', 'api_public_reviews',
                                        'sf_public_reviews', 'sf_search_listings', 'sf_search_exchanges', 'sf_search_technicians')
                then 'anon, authenticated'
            when v_function.proname in ('api_mail_claim', 'api_mail_mark', 'sf_mail_claim', 'sf_mail_mark')
                then 'service_role'
            when v_function.proname like 'api\_%' or v_function.proname like 'sf\_%'
                then 'authenticated'
        end;
        if v_audience is not null then
            execute format('grant execute on function %s to %s', v_function.signature, v_audience);
        end if;
    end loop;
end;
$$;
