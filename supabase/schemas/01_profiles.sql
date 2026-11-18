-- Private account data. No public SELECT policy is provided for this table.
create table public.profiles (
    id uuid primary key references auth.users(id) on delete cascade,
    account_type text not null check (account_type in ('customer', 'technician')),
    display_name text not null check (char_length(display_name) between 1 and 100),
    location text not null default '',
    avatar_path text,
    created_at timestamptz not null default now()
);

alter table public.profiles enable row level security;
revoke all on public.profiles from anon, authenticated;
grant select, insert on public.profiles to authenticated;
grant update (display_name, location, avatar_path) on public.profiles to authenticated;

create policy profiles_read_own on public.profiles for select to authenticated
using ((select auth.uid()) = id);

create policy profiles_create_own on public.profiles for insert to authenticated
with check ((select auth.uid()) = id);

create policy profiles_update_own on public.profiles for update to authenticated
using ((select auth.uid()) = id)
with check ((select auth.uid()) = id);
