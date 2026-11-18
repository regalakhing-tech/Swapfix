import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createDatabase } from './harness.mjs';

test('single-file installer creates 18 protected tables and preserves unrelated records',async () => {
  const t = await createDatabase({installer:true});
  try {
    const rows = await t.admin("select tablename, rowsecurity from pg_tables where schemaname = 'public' and tablename <> 'plain_text_submissions'");
    assert.equal(rows.length,18);
    assert.ok(rows.every(row => row.rowsecurity));
    assert.deepEqual(await t.admin('select content from public.plain_text_submissions'),[{content:'keep me'}]);
    const install = await readFile(new URL('../../supabase/install.sql',import.meta.url),'utf8');
    await assert.rejects(t.db.exec(install),/already exists/);
    await t.db.exec('rollback');
    assert.deepEqual(await t.admin('select content from public.plain_text_submissions'),[{content:'keep me'}]);
  } finally { await t.db.close(); }
});
