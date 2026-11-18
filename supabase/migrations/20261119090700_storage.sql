-- SwapFix 7/8: storage buckets and object policies.
-- Object names are always <uploader-uuid>/<32-hex>.<ext>.
-- Public buckets are readable by URL. Private buckets are read through short-lived signed URLs,
-- which Storage issues only when the SELECT policies below allow the requesting user.

insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types) values
    ('avatars', 'avatars', true, 5242880, array['image/jpeg', 'image/png', 'image/webp']),
    ('listing-images', 'listing-images', true, 5242880, array['image/jpeg', 'image/png', 'image/webp']),
    ('repair-images', 'repair-images', false, 5242880, array['image/jpeg', 'image/png', 'image/webp']),
    ('offer-images', 'offer-images', false, 5242880, array['image/jpeg', 'image/png', 'image/webp'])
on conflict (id) do update set
    public = excluded.public,
    file_size_limit = excluded.file_size_limit,
    allowed_mime_types = excluded.allowed_mime_types;

-- Starter policy names, in case an earlier starter script was applied to this project.
drop policy if exists swapfix_images_insert_own on storage.objects;
drop policy if exists swapfix_images_select_own on storage.objects;
drop policy if exists swapfix_images_delete_own on storage.objects;

create policy swapfix_objects_insert_own on storage.objects for insert to authenticated
with check (
    bucket_id in ('avatars', 'listing-images', 'repair-images', 'offer-images')
    and (storage.foldername(name))[1] = (select auth.uid())::text
    and name ~ '^[0-9a-f-]{36}/[a-f0-9]{32}\.(jpg|png|webp)$'
);

create policy swapfix_objects_select_own on storage.objects for select to authenticated
using (
    bucket_id in ('avatars', 'listing-images', 'repair-images', 'offer-images')
    and (storage.foldername(name))[1] = (select auth.uid())::text
);

-- repair_requests RLS limits this to the job's customer and technician.
create policy swapfix_repair_objects_select_participant on storage.objects for select to authenticated
using (
    bucket_id = 'repair-images'
    and exists (select 1 from public.repair_requests r where r.photo_paths @> array[storage.objects.name])
);

-- trade_offer_terms RLS limits this to the offer's two parties.
create policy swapfix_offer_objects_select_participant on storage.objects for select to authenticated
using (
    bucket_id = 'offer-images'
    and exists (select 1 from public.trade_offer_terms t where t.photo_paths @> array[storage.objects.name])
);

-- Photos attached to a repair or offer are part of its record and cannot be deleted.
create policy swapfix_objects_delete_own on storage.objects for delete to authenticated
using (
    bucket_id in ('avatars', 'listing-images', 'repair-images', 'offer-images')
    and (storage.foldername(name))[1] = (select auth.uid())::text
    and not (bucket_id = 'repair-images'
             and exists (select 1 from public.repair_requests r where r.photo_paths @> array[storage.objects.name]))
    and not (bucket_id = 'offer-images'
             and exists (select 1 from public.trade_offer_terms t where t.photo_paths @> array[storage.objects.name]))
);
