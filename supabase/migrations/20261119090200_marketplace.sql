-- SwapFix 2/8: Buy & Sell listings.

create table public.product_listings (
    id uuid primary key default gen_random_uuid(),
    owner_id uuid not null references public.profiles (id),
    title text not null check (char_length(title) between 3 and 150),
    description text not null check (char_length(description) between 1 and 4000),
    category text not null check (category in ('cellphone', 'tablet', 'laptop', 'accessory', 'spare_part')),
    brand text not null check (char_length(brand) between 1 and 60),
    model text not null default '' check (char_length(model) <= 80),
    condition text not null check (condition in ('new', 'like_new', 'good', 'fair', 'for_parts')),
    known_defects text not null default '' check (char_length(known_defects) <= 2000),
    price numeric(12, 2) not null check (price >= 0),
    location text not null check (char_length(location) between 2 and 120),
    photo_paths text[] not null default '{}',
    status text not null default 'available' check (status in ('available', 'reserved', 'sold', 'removed')),
    status_changed_at timestamptz not null default now(),
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    search tsvector generated always as (
        to_tsvector('simple'::regconfig, title || ' ' || brand || ' ' || model || ' ' || description)
    ) stored,
    constraint product_listings_photos_owned check (swapfix_private.valid_photo_paths(photo_paths, owner_id, 8))
);
create index product_listings_owner_idx on public.product_listings (owner_id, created_at desc);
create index product_listings_browse_idx on public.product_listings (status, created_at desc);
create index product_listings_category_idx on public.product_listings (status, category, created_at desc);
create index product_listings_price_idx on public.product_listings (status, price);
create index product_listings_brand_idx on public.product_listings (lower(brand));
create index product_listings_search_idx on public.product_listings using gin (search);
create trigger product_listings_touch before update on public.product_listings
for each row execute function swapfix_private.touch_updated_at();

create function swapfix_private.api_save_listing(
    p_id uuid, p_title text, p_description text, p_category text, p_brand text, p_model text,
    p_condition text, p_known_defects text, p_price numeric, p_location text, p_photo_paths text[]
) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_title text := swapfix_private.clean_text(p_title, 'Title', 3, 150);
    v_description text := swapfix_private.clean_text(p_description, 'Description', 1, 4000);
    v_brand text := swapfix_private.clean_text(p_brand, 'Brand', 1, 60);
    v_model text := swapfix_private.clean_text(p_model, 'Model', 0, 80);
    v_defects text := swapfix_private.clean_text(p_known_defects, 'Known defects', 0, 2000);
    v_location text := swapfix_private.clean_text(p_location, 'Location', 2, 120);
    v_price numeric := swapfix_private.clean_money(p_price, 'Price', 0);
    v_photos text[] := swapfix_private.clean_photo_paths(p_photo_paths, v_user, 8);
    v_current public.product_listings;
    v_id uuid;
begin
    if p_category is null or p_category not in ('cellphone', 'tablet', 'laptop', 'accessory', 'spare_part') then
        perform swapfix_private.fail('SF422', 'Choose a valid category.');
    end if;
    if p_condition is null or p_condition not in ('new', 'like_new', 'good', 'fair', 'for_parts') then
        perform swapfix_private.fail('SF422', 'Choose a valid condition.');
    end if;
    if p_id is null then
        insert into public.product_listings (
            owner_id, title, description, category, brand, model, condition, known_defects, price, location, photo_paths
        ) values (
            v_user, v_title, v_description, p_category, v_brand, v_model, p_condition, v_defects, v_price, v_location, v_photos
        ) returning id into v_id;
        return jsonb_build_object('id', v_id, 'removed_paths', '[]'::jsonb);
    end if;
    select * into v_current from public.product_listings where id = p_id for update;
    if not found or v_current.owner_id <> v_user then
        perform swapfix_private.fail('SF404', 'Listing not found.');
    end if;
    if v_current.status not in ('available', 'reserved') then
        perform swapfix_private.fail('SF409', 'Sold or removed listings cannot be edited.');
    end if;
    update public.product_listings set
        title = v_title, description = v_description, category = p_category, brand = v_brand, model = v_model,
        condition = p_condition, known_defects = v_defects, price = v_price, location = v_location, photo_paths = v_photos
    where id = p_id;
    return jsonb_build_object('id', p_id, 'removed_paths', (
        select coalesce(jsonb_agg(path), '[]'::jsonb) from unnest(v_current.photo_paths) as path where path <> all (v_photos)
    ));
end;
$$;

create function swapfix_private.api_set_listing_status(p_id uuid, p_status text, p_expected_status text) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
    v_user uuid := swapfix_private.require_profile();
    v_current public.product_listings;
begin
    select * into v_current from public.product_listings where id = p_id for update;
    if not found or v_current.owner_id <> v_user then
        perform swapfix_private.fail('SF404', 'Listing not found.');
    end if;
    if v_current.status is distinct from p_expected_status then
        perform swapfix_private.fail('SF409', 'This listing changed. Refresh and try again.');
    end if;
    if not ((v_current.status, p_status) in (
        ('available', 'reserved'), ('available', 'sold'), ('available', 'removed'),
        ('reserved', 'available'), ('reserved', 'sold'), ('reserved', 'removed')
    )) then
        perform swapfix_private.fail('SF409', 'That availability change is not allowed.');
    end if;
    update public.product_listings set status = p_status, status_changed_at = now() where id = p_id;
    return jsonb_build_object('id', p_id, 'status', p_status);
end;
$$;

-- Public browsing. SECURITY INVOKER: RLS still applies.
create function public.sf_search_listings(
    p_query text default null, p_category text default null, p_brand text default null,
    p_condition text default null, p_min_price numeric default null, p_max_price numeric default null,
    p_location text default null, p_sort text default 'newest', p_limit integer default 24, p_offset integer default 0
) returns jsonb
language sql stable security invoker set search_path = '' as $$
    with filtered as (
        select l.* from public.product_listings l
        where l.status in ('available', 'reserved')
          and (nullif(btrim(p_query), '') is null or l.search @@ swapfix_private.prefix_tsquery(p_query))
          and (nullif(p_category, '') is null or l.category = p_category)
          and (nullif(btrim(p_brand), '') is null or lower(l.brand) = lower(btrim(p_brand)))
          and (nullif(p_condition, '') is null or l.condition = p_condition)
          and (p_min_price is null or l.price >= p_min_price)
          and (p_max_price is null or l.price <= p_max_price)
          and (nullif(btrim(p_location), '') is null or l.location ilike swapfix_private.like_pattern(p_location))
    ), page as (
        select f.*, row_number() over (
            order by
                case when p_sort = 'price_asc' then f.price end asc nulls last,
                case when p_sort = 'price_desc' then f.price end desc nulls last,
                f.created_at desc, f.id
        ) as position
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
                'condition', p.condition, 'known_defects', p.known_defects, 'price', p.price, 'location', p.location,
                'photo_paths', p.photo_paths, 'status', p.status, 'created_at', p.created_at,
                'owner', jsonb_build_object('id', o.id, 'display_name', o.display_name, 'avatar_path', o.avatar_path)
            ) order by p.position)
            from page p join public.profiles o on o.id = p.owner_id
        ), '[]'::jsonb)
    );
$$;
