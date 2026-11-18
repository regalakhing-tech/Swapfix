-- Only deliberately public technician fields belong here. Never add email or private contact data.
create table public.technician_profiles (
    user_id uuid primary key references public.profiles(id) on delete cascade,
    public_name text not null check (char_length(public_name) between 1 and 100),
    location text not null default '',
    photo_path text,
    specialties text[] not null default '{}',
    supported_brands text[] not null default '{}',
    service_area text not null default '',
    available_hours text not null default '',
    created_at timestamptz not null default now()
);

create index technician_profiles_location_idx on public.technician_profiles (location);
create index technician_profiles_specialties_idx on public.technician_profiles using gin (specialties);
create index technician_profiles_brands_idx on public.technician_profiles using gin (supported_brands);
alter table public.technician_profiles enable row level security;
revoke all on public.technician_profiles from anon, authenticated;
grant select on public.technician_profiles to anon, authenticated;
grant insert on public.technician_profiles to authenticated;
grant update (public_name, location, photo_path, specialties, supported_brands, service_area, available_hours)
on public.technician_profiles to authenticated;

create policy technicians_public_read on public.technician_profiles for select to anon, authenticated
using (true);

create policy technicians_create_own on public.technician_profiles for insert to authenticated
with check (
    (select auth.uid()) = user_id and exists (
        select 1 from public.profiles p where p.id = (select auth.uid()) and p.account_type = 'technician'
    )
);

create policy technicians_update_own on public.technician_profiles for update to authenticated
using ((select auth.uid()) = user_id)
with check (
    (select auth.uid()) = user_id and exists (
        select 1 from public.profiles p where p.id = (select auth.uid()) and p.account_type = 'technician'
    )
);
