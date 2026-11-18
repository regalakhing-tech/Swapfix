-- Bind handover actions to the accepted offer shown when the form was rendered.
-- Keep the original RPC signatures available while the browser application rolls out.
create function swapfix_private.require_expected_agreement(
    p_exchange_id uuid, p_expected_offer_id uuid, p_owner_only boolean
) returns void
language plpgsql security invoker set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_exchange public.exchange_listings;
begin
    -- Lock the exchange before touching any offers, as in the existing mutations.
    select * into v_exchange from public.exchange_listings where id = p_exchange_id for update;
    if not found or (v_exchange.owner_id <> v_user and (p_owner_only or not exists (
        select 1 from public.trade_offers
        where id = v_exchange.accepted_offer_id and offerer_id = v_user
    ))) then
        perform swapfix_private.fail('SF404', 'Exchange listing not found.');
    end if;
    if p_expected_offer_id is null or v_exchange.status <> 'agreed'
        or v_exchange.accepted_offer_id is distinct from p_expected_offer_id then
        perform swapfix_private.fail('SF409', 'This agreement changed. Refresh and try again.');
    end if;
end;
$$;

create function swapfix_private.api_complete_exchange(p_exchange_id uuid, p_expected_offer_id uuid) returns jsonb
language plpgsql security definer set search_path = '' as $$
begin
    perform swapfix_private.require_expected_agreement(p_exchange_id, p_expected_offer_id, true);
    return swapfix_private.api_complete_exchange(p_exchange_id);
end;
$$;

create function swapfix_private.api_cancel_agreement(p_exchange_id uuid, p_note text, p_expected_offer_id uuid) returns jsonb
language plpgsql security definer set search_path = '' as $$
begin
    perform swapfix_private.require_expected_agreement(p_exchange_id, p_expected_offer_id, false);
    return swapfix_private.api_cancel_agreement(p_exchange_id, p_note);
end;
$$;

create function public.sf_complete_exchange(p_exchange_id uuid, p_expected_offer_id uuid) returns jsonb
language sql security invoker set search_path = '' as $$
    select swapfix_private.api_complete_exchange(p_exchange_id, p_expected_offer_id);
$$;

create function public.sf_cancel_agreement(p_exchange_id uuid, p_note text, p_expected_offer_id uuid) returns jsonb
language sql security invoker set search_path = '' as $$
    select swapfix_private.api_cancel_agreement(p_exchange_id, p_note, p_expected_offer_id);
$$;

revoke all on function swapfix_private.require_expected_agreement(uuid, uuid, boolean),
    swapfix_private.api_complete_exchange(uuid, uuid), swapfix_private.api_cancel_agreement(uuid, text, uuid),
    public.sf_complete_exchange(uuid, uuid), public.sf_cancel_agreement(uuid, text, uuid)
    from public, anon, authenticated, service_role;

grant execute on function swapfix_private.api_complete_exchange(uuid, uuid),
    swapfix_private.api_cancel_agreement(uuid, text, uuid),
    public.sf_complete_exchange(uuid, uuid), public.sf_cancel_agreement(uuid, text, uuid)
    to authenticated;

notify pgrst, 'reload schema';
