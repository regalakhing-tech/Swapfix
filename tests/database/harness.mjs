// Local PostgreSQL (PGlite) harness. Auth and Storage are small stubs that mirror the parts of
// Supabase the migrations rely on. This is not a hosted Supabase integration test.
import { readdir, readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { PGlite } from '@electric-sql/pglite';

const STUBS = `
create role anon nologin;
create role authenticated nologin;
create role service_role nologin bypassrls;
create schema auth;
create table auth.users (id uuid primary key, email text);
create function auth.uid() returns uuid language sql stable as $$
    select nullif(nullif(current_setting('request.jwt.claims', true), '')::jsonb ->> 'sub', '')::uuid
$$;
grant usage on schema auth to anon, authenticated, service_role;
grant execute on function auth.uid() to anon, authenticated, service_role;
create schema storage;
create table storage.buckets (id text primary key, name text, public boolean, file_size_limit bigint, allowed_mime_types text[]);
create table storage.objects (id uuid primary key default gen_random_uuid(), bucket_id text references storage.buckets (id), name text, unique (bucket_id, name));
alter table storage.objects enable row level security;
create function storage.foldername(name text) returns text[] language sql immutable as $$
    select (string_to_array(name, '/'))[1 : cardinality(string_to_array(name, '/')) - 1]
$$;
grant usage on schema storage to anon, authenticated, service_role;
grant select, insert, delete on storage.objects to authenticated;
grant execute on function storage.foldername(text) to anon, authenticated, service_role;
-- Hosted Supabase exposes new public objects to the API roles by default.
alter default privileges in schema public grant all on tables to anon, authenticated, service_role;
alter default privileges in schema public grant all on functions to anon, authenticated, service_role;
-- An unrelated table that must survive migrations untouched.
create table public.plain_text_submissions (id serial primary key, content text);
insert into public.plain_text_submissions (content) values ('keep me');
`;

export async function createDatabase({ installer = false } = {}) {
  const db = new PGlite();
  await db.exec(STUBS);
  const directory = new URL('../../supabase/migrations/', import.meta.url);
  const files = (await readdir(directory)).filter(name => name.endsWith('.sql')).sort();
  assert.ok(files.length > 0, 'Migrations must exist.');
  if (installer) {
    await db.exec(await readFile(new URL('../../supabase/install.sql', import.meta.url), 'utf8'));
  } else {
    for (const file of files) await db.exec(await readFile(new URL(file, directory), 'utf8'));
  }
  return new Harness(db);
}

class Harness {
  constructor(db) { this.db = db; }

  async admin(sql, params = []) {
    await this.db.exec("reset role; select set_config('request.jwt.claims', '', false);");
    return (await this.db.query(sql, params)).rows;
  }

  async #become(role, userId, extraClaims) {
    const claims = userId ? JSON.stringify({ sub: userId, role, ...extraClaims }) : '';
    await this.db.exec('reset role;');
    await this.db.query("select set_config('request.jwt.claims', $1, false)", [claims]);
    await this.db.exec(`set role ${role};`);
  }

  async query(userId, sql, params = [], role = userId ? 'authenticated' : 'anon', extraClaims = {}) {
    await this.#become(role, userId, extraClaims);
    try { return (await this.db.query(sql, params)).rows; } finally { await this.db.exec('reset role;'); }
  }

  async rpc(userId, name, args = {}, role = userId ? 'authenticated' : 'anon', extraClaims = {}) {
    const keys = Object.keys(args);
    const list = keys.map((key, index) => `${key} => $${index + 1}`).join(', ');
    const rows = await this.query(userId, `select public.${name}(${list}) as result`, keys.map(key => args[key]), role, extraClaims);
    return rows[0].result;
  }

  async user(accountType, displayName = `${accountType} user`) {
    const id = randomUUID();
    await this.admin('insert into auth.users (id, email) values ($1, $2)', [id, `${id}@example.test`]);
    await this.rpc(id, 'sf_create_profile', { p_account_type: accountType, p_display_name: displayName, p_location: 'Manila' });
    return id;
  }

  async technician(name = 'Tech') {
    const id = await this.user('technician', name);
    await this.rpc(id, 'sf_save_technician_profile', {
      p_headline: 'Phone specialist', p_bio: '', p_location: 'Quezon City', p_service_area: 'Metro Manila',
      p_available_hours: 'Mon-Sat 9-6', p_specialties: ['Screen replacement'], p_supported_brands: ['Apple'], p_supported_models: ['iPhone 13'],
    });
    return id;
  }
}

export async function rejects(promise, code, pattern) {
  await assert.rejects(promise, error => {
    assert.equal(error.code, code, `Expected ${code}, received ${error.code}: ${error.message}`);
    if (pattern) assert.match(error.message, pattern);
    return true;
  });
}

export const photo = (owner, seed = 'a', extension = 'jpg') => `${owner}/${seed.repeat(32).slice(0, 32)}.${extension}`;
