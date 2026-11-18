create table public.product_listings (
    id uuid primary key default gen_random_uuid(),
    owner_id uuid not null references public.profiles(id) on delete cascade,
    title text not null check (char_length(title) between 1 and 150),
    description text not null default '',
    category text not null check (category in ('cellphone', 'tablet', 'laptop', 'accessory', 'spare_part')),
    brand text not null default '',
    model text not null default '',
    condition text not null check (condition in ('new', 'used', 'refurbished', 'for_parts')),
    known_defects text not null default '',
    price numeric(12, 2) not null check (price >= 0),
    location text not null,
    photo_paths text[] not null default '{}' check (cardinality(photo_paths) <= 8),
    status text not null default 'available' check (status in ('available', 'reserved', 'sold', 'removed')),
    created_at timestamptz not null default now()
);

create index product_listings_owner_idx on public.product_listings (owner_id);
create index product_listings_browse_idx on public.product_listings (status, category, created_at desc);
create index product_listings_brand_idx on public.product_listings (brand);
create index product_listings_location_idx on public.product_listings (location);
create index product_listings_price_idx on public.product_listings (price);
alter table public.product_listings enable row level security;
revoke all on public.product_listings from anon, authenticated;
grant select on public.product_listings to anon, authenticated;
grant insert, delete on public.product_listings to authenticated;
grant update (title, description, category, brand, model, condition, known_defects, price, location, photo_paths, status)
on public.product_listings to authenticated;

create policy listings_public_browse on public.product_listings for select to anon, authenticated
using (status in ('available', 'reserved'));

create policy listings_owner_read on public.product_listings for select to authenticated
using ((select auth.uid()) = owner_id);

create policy listings_owner_create on public.product_listings for insert to authenticated
with check ((select auth.uid()) = owner_id and status = 'available');

create policy listings_owner_update on public.product_listings for update to authenticated
using ((select auth.uid()) = owner_id)
with check ((select auth.uid()) = owner_id);

create policy listings_owner_delete on public.product_listings for delete to authenticated
using ((select auth.uid()) = owner_id);
