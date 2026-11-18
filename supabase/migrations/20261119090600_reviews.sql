-- SwapFix 6/8: reviews. Rating scale is 1 to 5 whole stars; one review per completed repair.

create table public.reviews (
    id uuid primary key default gen_random_uuid(),
    repair_id uuid not null unique references public.repair_requests (id),
    customer_id uuid not null references public.profiles (id),
    technician_id uuid not null references public.technician_profiles (user_id),
    rating smallint not null check (rating between 1 and 5),
    comment text not null default '' check (char_length(comment) <= 2000),
    created_at timestamptz not null default now(),
    constraint reviews_not_self check (customer_id <> technician_id)
);
create index reviews_technician_idx on public.reviews (technician_id, created_at desc);
create index reviews_customer_idx on public.reviews (customer_id, created_at desc);
create trigger reviews_immutable before update on public.reviews
for each row execute function swapfix_private.forbid_update();

create function swapfix_private.api_create_review(p_repair_id uuid, p_rating integer, p_comment text default '') returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_comment text := swapfix_private.clean_text(p_comment, 'Review', 0, 2000);
    v_repair public.repair_requests := swapfix_private.lock_repair_as_customer(p_repair_id, v_user);
    v_id uuid;
begin
    if p_rating is null or p_rating not between 1 and 5 then
        perform swapfix_private.fail('SF422', 'Choose a rating from 1 to 5.');
    end if;
    if v_repair.status <> 'completed' then
        perform swapfix_private.fail('SF409', 'You can review a repair after it is completed.');
    end if;
    -- Serialize reviews for the same technician before INSERT/aggregate. This
    -- separate statement lets the following query see the preceding commit at
    -- READ COMMITTED, even when customers review different repair rows.
    perform 1 from public.technician_profiles where user_id = v_repair.technician_id for update;
    insert into public.reviews (repair_id, customer_id, technician_id, rating, comment)
    values (p_repair_id, v_user, v_repair.technician_id, p_rating, v_comment)
    on conflict (repair_id) do nothing
    returning id into v_id;
    if v_id is null then
        perform swapfix_private.fail('SF409', 'You already reviewed this repair.');
    end if;
    update public.technician_profiles t set
        rating_count = totals.total,
        rating_average = totals.average
    from (
        select count(*)::integer as total, round(avg(rating), 2) as average
        from public.reviews where technician_id = v_repair.technician_id
    ) totals
    where t.user_id = v_repair.technician_id;
    perform swapfix_private.add_repair_event(p_repair_id, v_user, 'review_submitted', v_id, null, '');
    perform swapfix_private.notify(
        v_repair.technician_id, 'review', 'New ' || p_rating || '-star review',
        v_repair.device_brand || ' ' || v_repair.device_model, '/repairs/' || p_repair_id, 'repair', p_repair_id
    );
    return jsonb_build_object('id', v_id);
end;
$$;

-- Public review feed: rating, text, date and reviewer display name only.
-- It never returns repair, customer or device identifiers.
create function swapfix_private.api_public_reviews(p_technician_id uuid, p_limit integer default 20, p_offset integer default 0)
returns table (rating smallint, comment text, created_at timestamptz, reviewer_name text)
language sql stable security definer set search_path = '' as $$
    select r.rating, r.comment, r.created_at, p.display_name
    from public.reviews r
    join public.profiles p on p.id = r.customer_id
    where r.technician_id = p_technician_id
    order by r.created_at desc
    limit least(greatest(coalesce(p_limit, 20), 1), 50)
    offset least(greatest(coalesce(p_offset, 0), 0), 10000);
$$;
