// Run: node --test tests/database/schema.test.mjs
import { after, before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { createDatabase, photo, rejects } from './harness.mjs';

let t;
before(async () => { t = await createDatabase(); });
after(async () => { await t?.db.close(); });

const tomorrow = new Date(Date.now() + 86_400_000).toISOString().slice(0, 10);
const listingArgs = (over = {}) => ({
  p_id: null, p_title: 'iPhone 13 128GB', p_description: 'Clean unit', p_category: 'cellphone', p_brand: 'Apple',
  p_model: 'iPhone 13', p_condition: 'good', p_known_defects: 'Hairline scratch', p_price: '18500.50',
  p_location: 'Makati', p_photo_paths: [], ...over,
});
const exchangeArgs = (over = {}) => ({
  p_id: null, p_title: 'Pixel 7 for trade', p_description: '', p_category: 'cellphone', p_brand: 'Google', p_model: 'Pixel 7',
  p_condition: 'good', p_known_defects: '', p_location: 'Pasig', p_photo_paths: [], p_wanted_item: 'iPhone 13',
  p_wanted_details: '', p_cash_preference: 'either', ...over,
});
const offerArgs = (exchange, over = {}) => ({
  p_exchange_id: exchange, p_device_title: 'iPhone 12', p_brand: 'Apple', p_model: '12', p_condition: 'fair',
  p_known_defects: 'Battery 80%', p_message: 'Trade?', p_cash_amount: '1500.00', p_cash_payer: 'offerer', p_photo_paths: [], ...over,
});
const counterArgs = (offer, version, over = {}) => ({
  p_offer_id: offer, p_expected_version: version, p_device_title: 'iPhone 12', p_brand: 'Apple', p_model: '12',
  p_condition: 'fair', p_known_defects: 'Battery 80%', p_message: 'Add more cash', p_cash_amount: '3000.00', p_cash_payer: 'offerer', ...over,
});
const repairArgs = (technician, over = {}) => ({
  p_technician_id: technician, p_service_id: null, p_device_type: 'cellphone', p_device_brand: 'Apple',
  p_device_model: 'iPhone 13', p_problem: 'Cracked screen', p_photo_paths: [], ...over,
});
const quote = (repair, over = {}) => ({
  p_repair_id: repair, p_scope: 'Replace display', p_parts: 'OLED panel', p_cost: '4500.00', p_estimated_completion: tomorrow, ...over,
});

async function acceptedRepair(customer, technician) {
  const repair = (await t.rpc(customer, 'sf_create_repair_request', repairArgs(technician))).id;
  const quotation = (await t.rpc(technician, 'sf_send_quotation', quote(repair))).id;
  await t.rpc(customer, 'sf_decide_quotation', { p_quotation_id: quotation, p_decision: 'accepted' });
  return { repair, quotation };
}
const advance = (technician, repair, from, to) =>
  t.rpc(technician, 'sf_update_repair_status', { p_repair_id: repair, p_expected_status: from, p_status: to });
async function completedRepair(customer, technician) {
  const { repair } = await acceptedRepair(customer, technician);
  await advance(technician, repair, 'accepted', 'in_repair');
  await advance(technician, repair, 'in_repair', 'ready_for_pickup');
  await advance(technician, repair, 'ready_for_pickup', 'completed');
  return repair;
}

describe('review regressions', () => {
  for (const action of ['sf_accept_offer', 'sf_decline_offer', 'sf_counter_offer']) {
    it(`${action} rejects a missing or nonpositive expected version after a counteroffer`, async () => {
      const owner = await t.user('customer');
      const offerer = await t.user('customer');
      const exchange = (await t.rpc(owner, 'sf_save_exchange', exchangeArgs())).id;
      const offer = (await t.rpc(offerer, 'sf_submit_offer', offerArgs(exchange))).id;
      await t.rpc(owner, 'sf_counter_offer', counterArgs(offer, 1));
      for (const invalid of [null, 0, -1]) {
        const args = action === 'sf_counter_offer' ? counterArgs(offer, invalid) : { p_offer_id: offer, p_expected_version: invalid };
        await rejects(t.rpc(offerer, action, args), 'SF409');
      }
      const rows = await t.query(owner, 'select current_version, status from public.trade_offers where id = $1', [offer]);
      assert.deepEqual(rows, [{ current_version: 2, status: 'pending' }]);
    });
  }

  it('keeps a long added-cost reason and creates its history entry atomically', async () => {
    const customer = await t.user('customer');
    const technician = await t.technician();
    const { repair } = await acceptedRepair(customer, technician);
    const reason = 'x'.repeat(1001);
    const added = await t.rpc(technician, 'sf_request_added_cost', { p_repair_id: repair, p_reason: reason, p_proposed_work: 'Replace damaged connector', p_amount: '100.00' });
    const rows = await t.query(customer, 'select reason from public.repair_added_costs where id = $1', [added.id]);
    assert.equal(rows[0].reason, reason);
    const events = await t.query(customer, "select note from public.repair_events where repair_id = $1 and kind = 'added_cost_requested'", [repair]);
    assert.equal(events.length, 1);
    assert.equal(events[0].note, reason.slice(0, 1000));
  });
});

describe('schema exposure', () => {
  it('enables RLS on every SwapFix table and grants the API roles SELECT at most', async () => {
    const tables = await t.admin(`
      select c.relname, c.relrowsecurity from pg_class c join pg_namespace n on n.oid = c.relnamespace
      where n.nspname = 'public' and c.relkind = 'r' and c.relname <> 'plain_text_submissions'`);
    assert.ok(tables.length >= 18);
    for (const table of tables) assert.equal(table.relrowsecurity, true, table.relname);
    const writes = await t.admin(`
      select table_name, grantee, privilege_type from information_schema.role_table_grants
      where table_schema = 'public' and table_name <> 'plain_text_submissions'
        and grantee in ('anon', 'authenticated', 'service_role', 'PUBLIC') and privilege_type <> 'SELECT'`);
    assert.deepEqual(writes, []);
    const outbox = await t.admin(`select 1 from information_schema.role_table_grants
      where table_name = 'mail_outbox' and grantee in ('anon', 'authenticated', 'service_role', 'PUBLIC')`);
    assert.deepEqual(outbox, []);
  });

  it('leaves unrelated tables and their grants alone', async () => {
    assert.deepEqual(await t.admin('select content from public.plain_text_submissions'), [{ content: 'keep me' }]);
    const grants = await t.admin(`select 1 from information_schema.role_table_grants
      where table_name = 'plain_text_submissions' and grantee = 'authenticated'`);
    assert.ok(grants.length > 0);
  });

  it('keeps SECURITY DEFINER out of the exposed schema and pins search_path everywhere', async () => {
    const functions = await t.admin(`
      select n.nspname, p.proname, p.prosecdef, p.proconfig from pg_proc p join pg_namespace n on n.oid = p.pronamespace
      where n.nspname = 'swapfix_private' or (n.nspname = 'public' and p.proname like 'sf\\_%')`);
    assert.ok(functions.length > 50);
    for (const fn of functions) {
      assert.ok(fn.proconfig?.some(item => item.startsWith('search_path=')), `${fn.proname} needs a fixed search_path`);
      if (fn.nspname === 'public') assert.equal(fn.prosecdef, false, `${fn.proname} must be SECURITY INVOKER`);
    }
  });

  it('never grants function execution to PUBLIC and limits anon to read functions', async () => {
    const rows = await t.admin(`
      select p.proname, r.rolname from pg_proc p join pg_namespace n on n.oid = p.pronamespace
      cross join lateral aclexplode(coalesce(p.proacl, acldefault('f', p.proowner))) a
      left join pg_roles r on r.oid = a.grantee
      where (n.nspname = 'swapfix_private' or (n.nspname = 'public' and p.proname like 'sf\\_%'))
        and a.privilege_type = 'EXECUTE' and (a.grantee = 0 or r.rolname = 'anon')`);
    assert.equal(rows.some(row => row.rolname === null), false, 'PUBLIC must not execute SwapFix functions');
    assert.deepEqual([...new Set(rows.map(row => row.proname))].sort(), [
      'api_public_reviews', 'like_pattern', 'prefix_tsquery', 'sf_public_reviews',
      'sf_search_exchanges', 'sf_search_listings', 'sf_search_technicians',
    ]);
  });

  it('rejects direct writes and anonymous mutations', async () => {
    const user = await t.user('customer');
    await rejects(t.query(user, "update public.profiles set account_type = 'technician' where id = $1", [user]), '42501');
    await rejects(t.query(user, "insert into public.notifications (recipient_id, kind, title, link_path, related_type, related_id) values ($1, 'review', 'x', '/', 'repair', $1)", [user]), '42501');
    await rejects(t.rpc(null, 'sf_save_listing', listingArgs()), '42501');
  });
});

describe('accounts', () => {
  it('stores the account type once and ignores claims for authorization', async () => {
    const user = await t.user('customer');
    await rejects(t.rpc(user, 'sf_create_profile', { p_account_type: 'technician', p_display_name: 'Again' }), 'SF409');
    await rejects(t.rpc(user, 'sf_save_repair_service', {
      p_id: null, p_name: 'Screen', p_description: '', p_supported_brands: [], p_supported_models: [], p_estimated_price: '100',
    }, 'authenticated', { user_metadata: { account_type: 'technician' }, app_metadata: { account_type: 'technician' } }), 'SF403');
  });

  it('keeps private profile fields private while the public card stays readable', async () => {
    const owner = await t.user('customer', 'Private Pat');
    const other = await t.user('customer');
    assert.deepEqual(await t.query(other, 'select location from public.profile_private where user_id = $1', [owner]), []);
    await rejects(t.query(null, 'select location from public.profile_private'), '42501');
    assert.equal((await t.query(null, 'select display_name from public.profiles where id = $1', [owner]))[0].display_name, 'Private Pat');
    const session = await t.rpc(owner, 'sf_session');
    assert.equal(session.profile.location, 'Manila');
    assert.equal(session.unread_notifications, 0);
  });

  it('accepts avatars only from the caller\'s folder and reports the replaced file', async () => {
    const user = await t.user('customer');
    const other = await t.user('customer');
    const update = path => ({ p_display_name: 'New Name', p_location: 'Cebu', p_avatar_path: path, p_clear_avatar: false, p_email_notifications: false });
    await rejects(t.rpc(user, 'sf_update_profile', update(photo(other))), 'SF422');
    await t.rpc(user, 'sf_update_profile', update(photo(user, 'a')));
    const result = await t.rpc(user, 'sf_update_profile', update(photo(user, 'b')));
    assert.equal(result.replaced_avatar_path, photo(user, 'a'));
  });
});

describe('marketplace', () => {
  it('lets only the owner edit and enforces availability transitions', async () => {
    const owner = await t.user('customer');
    const other = await t.user('technician');
    const id = (await t.rpc(owner, 'sf_save_listing', listingArgs())).id;
    await rejects(t.rpc(other, 'sf_save_listing', listingArgs({ p_id: id, p_title: 'Hijacked' })), 'SF404');
    await rejects(t.rpc(other, 'sf_set_listing_status', { p_id: id, p_status: 'sold', p_expected_status: 'available' }), 'SF404');
    await rejects(t.rpc(owner, 'sf_set_listing_status', { p_id: id, p_status: 'sold', p_expected_status: 'reserved' }), 'SF409');
    await t.rpc(owner, 'sf_set_listing_status', { p_id: id, p_status: 'reserved', p_expected_status: 'available' });
    await t.rpc(owner, 'sf_set_listing_status', { p_id: id, p_status: 'sold', p_expected_status: 'reserved' });
    await rejects(t.rpc(owner, 'sf_set_listing_status', { p_id: id, p_status: 'available', p_expected_status: 'sold' }), 'SF409');
    await rejects(t.rpc(owner, 'sf_save_listing', listingArgs({ p_id: id })), 'SF409');
  });

  it('keeps exact prices and rejects sub-centavo or negative values', async () => {
    const owner = await t.user('customer');
    const id = (await t.rpc(owner, 'sf_save_listing', listingArgs({ p_price: '1234567890.12' }))).id;
    assert.equal((await t.admin('select price::text from public.product_listings where id = $1', [id]))[0].price, '1234567890.12');
    await rejects(t.rpc(owner, 'sf_save_listing', listingArgs({ p_price: '10.005' })), 'SF422');
    await rejects(t.rpc(owner, 'sf_save_listing', listingArgs({ p_price: '-1' })), 'SF422');
  });

  it('rejects photo paths from another user and reports removed photos', async () => {
    const owner = await t.user('customer');
    const other = await t.user('customer');
    await rejects(t.rpc(owner, 'sf_save_listing', listingArgs({ p_photo_paths: [photo(other)] })), 'SF422');
    await rejects(t.rpc(owner, 'sf_save_listing', listingArgs({ p_photo_paths: [`${owner}/../evil.jpg`] })), 'SF422');
    const id = (await t.rpc(owner, 'sf_save_listing', listingArgs({ p_photo_paths: [photo(owner, 'a'), photo(owner, 'b')] }))).id;
    const saved = await t.rpc(owner, 'sf_save_listing', listingArgs({ p_id: id, p_photo_paths: [photo(owner, 'b')] }));
    assert.deepEqual(saved.removed_paths, [photo(owner, 'a')]);
  });

  it('searches with filters, bounds and escaped input, hiding sold and removed items', async () => {
    const owner = await t.user('customer');
    const brand = `Brand${randomUUID().slice(0, 8)}`;
    const save = over => t.rpc(owner, 'sf_save_listing', listingArgs({ p_brand: brand, ...over }));
    const cheap = (await save({ p_title: 'Budget handset', p_price: '100' })).id;
    const costly = (await save({ p_title: 'Flagship handset', p_price: '900', p_location: 'Davao City' })).id;
    const sold = (await save({ p_title: 'Sold handset' })).id;
    await t.rpc(owner, 'sf_set_listing_status', { p_id: sold, p_status: 'sold', p_expected_status: 'available' });
    const ids = result => result.items.map(item => item.id);
    const all = await t.rpc(null, 'sf_search_listings', { p_brand: brand.toLowerCase(), p_sort: 'price_desc' });
    assert.deepEqual(ids(all), [costly, cheap]);
    assert.equal(all.total, 2);
    assert.deepEqual(ids(await t.rpc(null, 'sf_search_listings', { p_brand: brand, p_query: 'flag', p_min_price: '500' })), [costly]);
    assert.deepEqual(ids(await t.rpc(null, 'sf_search_listings', { p_brand: brand, p_location: 'davao' })), [costly]);
    assert.equal((await t.rpc(null, 'sf_search_listings', { p_location: '100%_%' })).total, 0);
    assert.equal((await t.rpc(null, 'sf_search_listings', { p_brand: brand, p_query: `'):*&|!` })).items.length >= 0, true);
    assert.equal((await t.rpc(null, 'sf_search_listings', { p_brand: brand, p_limit: 5000 })).items.length, 2);
    assert.deepEqual(await t.query(null, 'select id from public.product_listings where id = $1', [sold]), []);
  });

  it('links inquiries to a participant conversation and blocks the owner', async () => {
    const owner = await t.user('customer');
    const buyer = await t.user('technician');
    const id = (await t.rpc(owner, 'sf_save_listing', listingArgs())).id;
    await rejects(t.rpc(owner, 'sf_start_listing_inquiry', { p_listing_id: id, p_message: 'Hi' }), 'SF403');
    const first = await t.rpc(buyer, 'sf_start_listing_inquiry', { p_listing_id: id, p_message: 'Still available?' });
    const second = await t.rpc(buyer, 'sf_start_listing_inquiry', { p_listing_id: id, p_message: 'Hello again' });
    assert.equal(first.conversation_id, second.conversation_id);
    await t.rpc(owner, 'sf_set_listing_status', { p_id: id, p_status: 'sold', p_expected_status: 'available' });
    assert.equal((await t.query(buyer, 'select id from public.product_listings where id = $1', [id])).length, 1, 'Inquirer keeps context after sale');
    await rejects(t.rpc(await t.user('customer'), 'sf_start_listing_inquiry', { p_listing_id: id, p_message: 'Late' }), 'SF404');
  });
});

describe('exchange', () => {
  async function setup() {
    const owner = await t.user('customer', 'Owner');
    const offerer = await t.user('technician', 'Offerer');
    const exchange = (await t.rpc(owner, 'sf_save_exchange', exchangeArgs())).id;
    const offer = (await t.rpc(offerer, 'sf_submit_offer', offerArgs(exchange))).id;
    return { owner, offerer, exchange, offer };
  }

  it('blocks self offers, duplicate active offers and ambiguous cash terms', async () => {
    const { owner, offerer, exchange } = await setup();
    await rejects(t.rpc(owner, 'sf_submit_offer', offerArgs(exchange)), 'SF403');
    await rejects(t.rpc(offerer, 'sf_submit_offer', offerArgs(exchange)), 'SF409');
    const third = await t.user('customer');
    await rejects(t.rpc(third, 'sf_submit_offer', offerArgs(exchange, { p_cash_amount: '100', p_cash_payer: 'none' })), 'SF422');
    await rejects(t.rpc(third, 'sf_submit_offer', offerArgs(exchange, { p_cash_amount: '0', p_cash_payer: 'owner' })), 'SF422');
  });

  it('alternates counteroffers, keeps every version and rejects stale decisions', async () => {
    const { owner, offerer, offer } = await setup();
    await rejects(t.rpc(offerer, 'sf_accept_offer', { p_offer_id: offer, p_expected_version: 1 }), 'SF403', /turn/);
    const counter = await t.rpc(owner, 'sf_counter_offer', counterArgs(offer, 1));
    assert.equal(counter.version, 2);
    assert.equal(counter.awaiting_user_id, offerer);
    await rejects(t.rpc(owner, 'sf_accept_offer', { p_offer_id: offer, p_expected_version: 2 }), 'SF403');
    await rejects(t.rpc(offerer, 'sf_accept_offer', { p_offer_id: offer, p_expected_version: 1 }), 'SF409', /changed/);
    const terms = await t.query(owner, 'select version, cash_amount::text, proposed_by from public.trade_offer_terms where offer_id = $1 order by version', [offer]);
    assert.deepEqual(terms.map(row => [row.version, row.cash_amount]), [[1, '1500.00'], [2, '3000.00']]);
    await rejects(t.admin('update public.trade_offer_terms set cash_amount = 1 where offer_id = $1', [offer]), 'SF409');
    await t.rpc(offerer, 'sf_accept_offer', { p_offer_id: offer, p_expected_version: 2 });
    const [row] = await t.admin('select status, accepted_version from public.trade_offers where id = $1', [offer]);
    assert.deepEqual(row, { status: 'accepted', accepted_version: 2 });
  });

  it('allows one accepted offer, separates acceptance from completion and reopens on cancel', async () => {
    const { owner, offerer, exchange, offer } = await setup();
    const rival = await t.user('customer');
    const rivalOffer = (await t.rpc(rival, 'sf_submit_offer', offerArgs(exchange))).id;
    await rejects(t.rpc(owner, 'sf_complete_exchange', { p_exchange_id: exchange }), 'SF409');
    await t.rpc(owner, 'sf_accept_offer', { p_offer_id: offer, p_expected_version: 1 });
    assert.equal((await t.admin('select status from public.exchange_listings where id = $1', [exchange]))[0].status, 'agreed');
    await rejects(t.rpc(owner, 'sf_accept_offer', { p_offer_id: rivalOffer, p_expected_version: 1 }), 'SF409');
    await rejects(t.rpc(rival, 'sf_cancel_agreement', { p_exchange_id: exchange, p_note: 'Mine now' }), 'SF404');
    await rejects(t.admin("update public.trade_offers set status = 'accepted', accepted_version = 1, awaiting_user_id = null where id = $1", [rivalOffer]), '23505');
    await t.rpc(offerer, 'sf_cancel_agreement', { p_exchange_id: exchange, p_note: 'Changed my mind' });
    assert.equal((await t.admin('select status from public.exchange_listings where id = $1', [exchange]))[0].status, 'open');
    await t.rpc(owner, 'sf_accept_offer', { p_offer_id: rivalOffer, p_expected_version: 1 });
    await rejects(t.rpc(rival, 'sf_complete_exchange', { p_exchange_id: exchange }), 'SF404');
    await t.rpc(owner, 'sf_complete_exchange', { p_exchange_id: exchange });
    assert.equal((await t.rpc(null, 'sf_search_exchanges', { p_brand: 'Google' })).items.some(item => item.id === exchange), false);
    assert.equal((await t.admin('select status from public.trade_offers where id = $1', [rivalOffer]))[0].status, 'completed');
  });

  it('cancels other pending offers when the exchange completes', async () => {
    const { owner, exchange, offer } = await setup();
    const rival = await t.user('customer');
    const rivalOffer = (await t.rpc(rival, 'sf_submit_offer', offerArgs(exchange))).id;
    await t.rpc(owner, 'sf_accept_offer', { p_offer_id: offer, p_expected_version: 1 });
    await t.rpc(owner, 'sf_complete_exchange', { p_exchange_id: exchange });
    assert.equal((await t.admin('select status from public.trade_offers where id = $1', [rivalOffer]))[0].status, 'cancelled');
    const notes = await t.query(rival, "select title from public.notifications where kind = 'trade_update'");
    assert.deepEqual(notes.map(row => row.title), ['Exchange closed']);
  });

  it('hides negotiations from outsiders and lets the offerer withdraw', async () => {
    const { owner, offerer, offer } = await setup();
    const outsider = await t.user('customer');
    for (const table of ['trade_offers']) assert.deepEqual(await t.query(outsider, `select id from public.${table} where id = $1`, [offer]), []);
    assert.deepEqual(await t.query(outsider, 'select id from public.trade_offer_terms where offer_id = $1', [offer]), []);
    await rejects(t.rpc(outsider, 'sf_decline_offer', { p_offer_id: offer, p_expected_version: 1 }), 'SF404');
    await rejects(t.rpc(owner, 'sf_withdraw_offer', { p_offer_id: offer }), 'SF404');
    await t.rpc(offerer, 'sf_withdraw_offer', { p_offer_id: offer });
    await rejects(t.rpc(owner, 'sf_accept_offer', { p_offer_id: offer, p_expected_version: 1 }), 'SF409');
    assert.deepEqual((await t.query(owner, 'select event from public.trade_offer_events where offer_id = $1 order by created_at', [offer])).map(row => row.event), ['offered', 'withdrawn']);
  });
});

describe('repair workflow', () => {
  it('restricts services to technician accounts and their owners', async () => {
    const technician = await t.technician();
    const rival = await t.technician();
    const customer = await t.user('customer');
    const args = { p_id: null, p_name: 'Battery swap', p_description: '', p_supported_brands: ['Apple'], p_supported_models: [], p_estimated_price: '900' };
    await rejects(t.rpc(customer, 'sf_save_repair_service', args), 'SF403');
    const id = (await t.rpc(technician, 'sf_save_repair_service', args)).id;
    await rejects(t.rpc(rival, 'sf_save_repair_service', { ...args, p_id: id }), 'SF404');
    await rejects(t.rpc(rival, 'sf_set_service_active', { p_id: id, p_active: false }), 'SF404');
    await rejects(t.rpc(customer, 'sf_create_repair_request', repairArgs(rival, { p_service_id: id })), 'SF422');
    await t.rpc(technician, 'sf_set_service_active', { p_id: id, p_active: false });
    assert.deepEqual(await t.query(null, 'select id from public.repair_services where id = $1', [id]), []);
    assert.equal((await t.query(technician, 'select id from public.repair_services where id = $1', [id])).length, 1);
  });

  it('finds technicians by area, specialty and brand without private fields', async () => {
    const technician = await t.technician('Findable Tech');
    const result = await t.rpc(null, 'sf_search_technicians', { p_area: 'metro man', p_specialty: 'screen', p_brand: 'apple', p_query: 'Findable' });
    const match = result.items.find(item => item.id === technician);
    assert.ok(match);
    assert.equal('email' in match, false);
    assert.equal((await t.rpc(null, 'sf_search_technicians', { p_query: 'Findable', p_brand: 'NoSuchBrand' })).total, 0);
  });

  it('blocks self-repair and requests to non-technicians', async () => {
    const technician = await t.technician();
    const customer = await t.user('customer');
    await rejects(t.rpc(technician, 'sf_create_repair_request', repairArgs(technician)), 'SF403');
    await rejects(t.rpc(technician, 'sf_create_repair_request', repairArgs(customer)), 'SF404');
  });

  it('requires quotation approval before work and only lets the assigned parties act', async () => {
    const technician = await t.technician();
    const rival = await t.technician();
    const customer = await t.user('customer');
    const repair = (await t.rpc(customer, 'sf_create_repair_request', repairArgs(technician))).id;
    await rejects(advance(technician, repair, 'request_sent', 'in_repair'), 'SF409');
    await rejects(t.rpc(customer, 'sf_send_quotation', quote(repair)), 'SF403');
    await rejects(t.rpc(rival, 'sf_send_quotation', quote(repair)), 'SF404');
    await rejects(t.rpc(technician, 'sf_send_quotation', quote(repair, { p_cost: '0' })), 'SF422');
    await rejects(t.rpc(technician, 'sf_send_quotation', quote(repair, { p_estimated_completion: '2000-01-01' })), 'SF422');
    const quotation = (await t.rpc(technician, 'sf_send_quotation', quote(repair))).id;
    await rejects(advance(technician, repair, 'quotation_sent', 'in_repair'), 'SF409');
    await rejects(t.rpc(technician, 'sf_decide_quotation', { p_quotation_id: quotation, p_decision: 'accepted' }), 'SF404');
    await rejects(t.rpc(rival, 'sf_decide_quotation', { p_quotation_id: quotation, p_decision: 'accepted' }), 'SF404');
    await t.rpc(customer, 'sf_decide_quotation', { p_quotation_id: quotation, p_decision: 'accepted' });
    const [row] = await t.admin('select status, approved_total::text from public.repair_requests where id = $1', [repair]);
    assert.deepEqual(row, { status: 'accepted', approved_total: '4500.00' });
    await rejects(t.rpc(customer, 'sf_decide_quotation', { p_quotation_id: quotation, p_decision: 'declined' }), 'SF409');
    await rejects(t.rpc(technician, 'sf_send_quotation', quote(repair, { p_cost: '1' })), 'SF409', /stage/);
  });

  it('keeps declined and superseded quotations, allowing a new version', async () => {
    const technician = await t.technician();
    const customer = await t.user('customer');
    const repair = (await t.rpc(customer, 'sf_create_repair_request', repairArgs(technician))).id;
    const first = (await t.rpc(technician, 'sf_send_quotation', quote(repair))).id;
    const second = (await t.rpc(technician, 'sf_send_quotation', quote(repair, { p_cost: '4000' }))).id;
    await rejects(t.rpc(customer, 'sf_decide_quotation', { p_quotation_id: first, p_decision: 'accepted' }), 'SF409');
    await t.rpc(customer, 'sf_decide_quotation', { p_quotation_id: second, p_decision: 'declined', p_note: 'Too high' });
    assert.equal((await t.admin('select status from public.repair_requests where id = $1', [repair]))[0].status, 'request_sent');
    const third = await t.rpc(technician, 'sf_send_quotation', quote(repair, { p_cost: '3500' }));
    assert.equal(third.version, 3);
    const rows = await t.query(customer, 'select version, status, cost::text, decision_note from public.repair_quotations where repair_id = $1 order by version', [repair]);
    assert.deepEqual(rows.map(row => [row.version, row.status, row.cost]), [[1, 'superseded', '4500.00'], [2, 'declined', '4000.00'], [3, 'pending', '3500.00']]);
    assert.equal(rows[1].decision_note, 'Too high');
  });

  it('freezes quotation terms and repair status order even for direct database writes', async () => {
    const technician = await t.technician();
    const customer = await t.user('customer');
    const { repair, quotation } = await acceptedRepair(customer, technician);
    await rejects(t.admin('update public.repair_quotations set cost = 1 where id = $1', [quotation]), 'SF409');
    await rejects(t.admin("update public.repair_requests set status = 'completed' where id = $1", [repair]), 'SF409');
  });

  it('applies added costs only after separate customer approval', async () => {
    const technician = await t.technician();
    const customer = await t.user('customer');
    const { repair } = await acceptedRepair(customer, technician);
    await advance(technician, repair, 'accepted', 'in_repair');
    const request = (over = {}) => ({ p_repair_id: repair, p_reason: 'Damaged connector', p_proposed_work: 'Replace connector', p_amount: '500.25', p_revised_completion: null, ...over });
    await rejects(t.rpc(customer, 'sf_request_added_cost', request()), 'SF403');
    const first = await t.rpc(technician, 'sf_request_added_cost', request());
    assert.equal(Number(first.revised_total), 5000.25);
    await rejects(t.rpc(technician, 'sf_request_added_cost', request()), 'SF409', /already/);
    assert.equal((await t.admin('select approved_total::text from public.repair_requests where id = $1', [repair]))[0].approved_total, '4500.00');
    await rejects(advance(technician, repair, 'in_repair', 'ready_for_pickup'), 'SF409', /pending added cost/);
    await rejects(t.rpc(technician, 'sf_decide_added_cost', { p_id: first.id, p_decision: 'accepted' }), 'SF404');
    await t.rpc(customer, 'sf_decide_added_cost', { p_id: first.id, p_decision: 'declined', p_note: 'No thanks' });
    assert.equal((await t.admin('select approved_total::text from public.repair_requests where id = $1', [repair]))[0].approved_total, '4500.00');
    const second = await t.rpc(technician, 'sf_request_added_cost', request({ p_amount: '100', p_revised_completion: tomorrow }));
    await t.rpc(customer, 'sf_decide_added_cost', { p_id: second.id, p_decision: 'accepted' });
    assert.equal((await t.admin('select approved_total::text from public.repair_requests where id = $1', [repair]))[0].approved_total, '4600.00');
    await rejects(t.rpc(customer, 'sf_decide_added_cost', { p_id: second.id, p_decision: 'declined' }), 'SF409');
    const third = await t.rpc(technician, 'sf_request_added_cost', request({ p_amount: '1' }));
    await t.rpc(technician, 'sf_withdraw_added_cost', { p_id: third.id });
    await advance(technician, repair, 'in_repair', 'ready_for_pickup');
    await rejects(t.rpc(technician, 'sf_request_added_cost', request()), 'SF409');
  });

  it('follows the status path, rejects stale updates and records timestamped history', async () => {
    const technician = await t.technician();
    const customer = await t.user('customer');
    const { repair } = await acceptedRepair(customer, technician);
    await rejects(advance(technician, repair, 'accepted', 'ready_for_pickup'), 'SF409');
    await rejects(advance(customer, repair, 'accepted', 'in_repair'), 'SF403');
    await advance(technician, repair, 'accepted', 'in_repair');
    await rejects(advance(technician, repair, 'accepted', 'in_repair'), 'SF409', /changed/);
    await advance(technician, repair, 'in_repair', 'ready_for_pickup');
    await advance(technician, repair, 'ready_for_pickup', 'completed');
    const events = await t.query(customer, "select to_status, actor_id, created_at from public.repair_events where repair_id = $1 and kind = 'status' order by created_at", [repair]);
    assert.deepEqual(events.map(row => row.to_status), ['request_sent', 'quotation_sent', 'accepted', 'in_repair', 'ready_for_pickup', 'completed']);
    assert.equal(events.at(-1).actor_id, technician);
    assert.ok((await t.admin('select completed_at from public.repair_requests where id = $1', [repair]))[0].completed_at);
    const notes = await t.query(customer, "select title from public.notifications where kind = 'repair_status' and related_id = $1", [repair]);
    assert.ok(notes.some(row => row.title === 'Repair: Ready for Pickup'));
  });

  it('applies cancellation rules and retains the record', async () => {
    const technician = await t.technician();
    const customer = await t.user('customer');
    const stranger = await t.user('customer');
    const { repair, quotation } = await acceptedRepair(customer, technician);
    await rejects(t.rpc(stranger, 'sf_cancel_repair', { p_repair_id: repair, p_reason: 'Nope' }), 'SF404');
    await advance(technician, repair, 'accepted', 'in_repair');
    await rejects(t.rpc(customer, 'sf_cancel_repair', { p_repair_id: repair, p_reason: 'Stop' }), 'SF409');
    await t.rpc(technician, 'sf_cancel_repair', { p_repair_id: repair, p_reason: 'Part unavailable' });
    const [row] = await t.admin('select status, cancelled_by, accepted_quotation_id from public.repair_requests where id = $1', [repair]);
    assert.deepEqual(row, { status: 'cancelled', cancelled_by: technician, accepted_quotation_id: quotation });
    await rejects(advance(technician, repair, 'cancelled', 'in_repair'), 'SF409');
  });

  it('shows repair records to participants only', async () => {
    const technician = await t.technician();
    const customer = await t.user('customer');
    const outsider = await t.technician();
    const { repair } = await acceptedRepair(customer, technician);
    for (const [table, column] of [['repair_requests', 'id'], ['repair_quotations', 'repair_id'], ['repair_events', 'repair_id'], ['repair_added_costs', 'repair_id']]) {
      assert.deepEqual(await t.query(outsider, `select 1 from public.${table} where ${column} = $1`, [repair]), [], table);
      await rejects(t.query(null, `select 1 from public.${table} where ${column} = $1`, [repair]), '42501');
    }
    assert.equal((await t.query(technician, 'select id from public.repair_requests where id = $1', [repair])).length, 1);
  });
});

describe('messaging and notifications', () => {
  it('restricts conversations to participants, tracks unread counts and marks read', async () => {
    const technician = await t.technician();
    const customer = await t.user('customer');
    const outsider = await t.user('customer');
    const { conversation_id: conversation } = await t.rpc(customer, 'sf_create_repair_request', repairArgs(technician));
    await rejects(t.rpc(outsider, 'sf_send_message', { p_conversation_id: conversation, p_body: 'Hi' }), 'SF404');
    await rejects(t.rpc(outsider, 'sf_mark_conversation_read', { p_conversation_id: conversation }), 'SF404');
    await rejects(t.rpc(customer, 'sf_send_message', { p_conversation_id: conversation, p_body: '   ' }), 'SF422');
    await t.rpc(customer, 'sf_send_message', { p_conversation_id: conversation, p_body: 'When can I drop it off?' });
    await t.rpc(customer, 'sf_send_message', { p_conversation_id: conversation, p_body: 'Tomorrow works.' });
    assert.deepEqual(await t.query(outsider, 'select id from public.messages where conversation_id = $1', [conversation]), []);
    assert.equal((await t.query(technician, 'select id from public.messages where conversation_id = $1', [conversation])).length, 2);
    const session = await t.rpc(technician, 'sf_session');
    assert.equal(session.unread_messages, 2);
    const unread = await t.query(technician, "select title, body from public.notifications where kind = 'message' and read_at is null");
    assert.equal(unread.length, 1, 'A busy chat keeps one unread notification');
    assert.match(unread[0].body, /Tomorrow works/);
    await t.rpc(technician, 'sf_mark_conversation_read', { p_conversation_id: conversation });
    const after = await t.rpc(technician, 'sf_session');
    assert.equal(after.unread_messages, 0);
    assert.equal((await t.query(technician, "select 1 from public.notifications where kind = 'message' and read_at is null")).length, 0);
    await rejects(t.query(technician, "update public.messages set body = 'edited'"), '42501');
  });

  it('marks only the recipient\'s notifications as read', async () => {
    const technician = await t.technician();
    const customer = await t.user('customer');
    await t.rpc(customer, 'sf_create_repair_request', repairArgs(technician));
    const [note] = await t.query(technician, "select id, link_path from public.notifications where kind = 'repair_request'");
    assert.match(note.link_path, /^\/repairs\//);
    assert.deepEqual(await t.query(customer, 'select id from public.notifications where id = $1', [note.id]), []);
    await rejects(t.rpc(customer, 'sf_mark_notification_read', { p_id: note.id }), 'SF404');
    assert.equal((await t.rpc(technician, 'sf_mark_notification_read', { p_id: note.id })).link_path, note.link_path);
    await t.rpc(technician, 'sf_mark_all_notifications_read');
    assert.equal((await t.rpc(technician, 'sf_session')).unread_notifications, 0);
  });

  it('queues email only for opted-in recipients and only the worker role can claim it', async () => {
    const technician = await t.technician();
    await t.rpc(technician, 'sf_update_profile', { p_display_name: 'Mail Tech', p_location: 'QC', p_avatar_path: null, p_clear_avatar: false, p_email_notifications: true });
    const customer = await t.user('customer');
    await t.rpc(customer, 'sf_create_repair_request', repairArgs(technician));
    await rejects(t.rpc(customer, 'sf_mail_claim', { p_limit: 5 }), '42501');
    const rows = await t.query(randomUUID(), 'select * from public.sf_mail_claim(p_limit => 50)', [], 'service_role');
    const mine = rows.filter(row => row.email === `${technician}@example.test`);
    assert.equal(mine.length, 1);
    assert.equal(mine[0].title, 'New repair request');
    await t.query(randomUUID(), 'select public.sf_mail_mark(p_outbox_id => $1, p_sent => true)', [mine[0].outbox_id], 'service_role');
    assert.equal((await t.admin('select status from public.mail_outbox where id = $1', [mine[0].outbox_id]))[0].status, 'sent');
  });
});

describe('reviews', () => {
  it('allows one review by the real customer after completion and updates public aggregates', async () => {
    const technician = await t.technician('Rated Tech');
    const customer = await t.user('customer', 'Reviewer Name');
    const outsider = await t.user('customer');
    const { repair: open } = await acceptedRepair(customer, technician);
    await rejects(t.rpc(customer, 'sf_create_review', { p_repair_id: open, p_rating: 5 }), 'SF409', /completed/);
    const repair = await completedRepair(customer, technician);
    await rejects(t.rpc(outsider, 'sf_create_review', { p_repair_id: repair, p_rating: 5 }), 'SF404');
    await rejects(t.rpc(technician, 'sf_create_review', { p_repair_id: repair, p_rating: 5 }), 'SF404');
    await rejects(t.rpc(customer, 'sf_create_review', { p_repair_id: repair, p_rating: 6 }), 'SF422');
    await rejects(t.rpc(customer, 'sf_create_review', { p_repair_id: repair, p_rating: 0 }), 'SF422');
    await t.rpc(customer, 'sf_create_review', { p_repair_id: repair, p_rating: 4, p_comment: 'Fast work' });
    await rejects(t.rpc(customer, 'sf_create_review', { p_repair_id: repair, p_rating: 5 }), 'SF409', /already/);
    const second = await completedRepair(customer, technician);
    await t.rpc(customer, 'sf_create_review', { p_repair_id: second, p_rating: 5 });
    const [profile] = await t.query(null, 'select rating_average::text, rating_count from public.technician_profiles where user_id = $1', [technician]);
    assert.deepEqual(profile, { rating_average: '4.50', rating_count: 2 });
    const feed = await t.query(null, 'select * from public.sf_public_reviews(p_technician_id => $1)', [technician]);
    assert.deepEqual(Object.keys(feed[0]).sort(), ['comment', 'created_at', 'rating', 'reviewer_name']);
    assert.equal(feed.find(row => row.comment === 'Fast work').reviewer_name, 'Reviewer Name');
    await rejects(t.query(null, 'select id from public.reviews'), '42501');
    assert.deepEqual(await t.query(outsider, 'select id from public.reviews where technician_id = $1', [technician]), []);
  });
});

describe('storage policies', () => {
  const addObject = (bucket, name) => t.admin('insert into storage.objects (bucket_id, name) values ($1, $2)', [bucket, name]);
  const visible = (user, bucket, name) => t.query(user, 'select name from storage.objects where bucket_id = $1 and name = $2', [bucket, name]);

  it('creates private repair and offer buckets with public listing and avatar buckets', async () => {
    const rows = await t.admin('select id, public from storage.buckets order by id');
    assert.deepEqual(rows, [
      { id: 'avatars', public: true }, { id: 'listing-images', public: true },
      { id: 'offer-images', public: false }, { id: 'repair-images', public: false },
    ]);
  });

  it('confines uploads to the caller\'s folder and generated file names', async () => {
    const user = await t.user('customer');
    const other = await t.user('customer');
    const insert = (bucket, name) => t.query(user, 'insert into storage.objects (bucket_id, name) values ($1, $2)', [bucket, name]);
    await insert('listing-images', photo(user, 'c'));
    await rejects(insert('listing-images', photo(other, 'c')), '42501');
    await rejects(insert('listing-images', `${user}/shell.php`), '42501');
    await rejects(insert('unknown', photo(user, 'd')), '42501');
  });

  it('shares repair photos with job participants only and protects them from deletion', async () => {
    const technician = await t.technician();
    const customer = await t.user('customer');
    const outsider = await t.user('customer');
    const name = photo(customer, 'e');
    await addObject('repair-images', name);
    assert.deepEqual(await visible(technician, 'repair-images', name), [], 'Unattached photo stays private');
    await t.rpc(customer, 'sf_create_repair_request', repairArgs(technician, { p_photo_paths: [name] }));
    assert.equal((await visible(technician, 'repair-images', name)).length, 1);
    assert.equal((await visible(customer, 'repair-images', name)).length, 1);
    assert.deepEqual(await visible(outsider, 'repair-images', name), []);
    await t.query(customer, 'delete from storage.objects where bucket_id = $1 and name = $2', ['repair-images', name]);
    assert.equal((await t.admin('select 1 from storage.objects where name = $1', [name])).length, 1, 'Attached photo must remain');
  });

  it('shares offer photos with the two parties only', async () => {
    const owner = await t.user('customer');
    const offerer = await t.user('customer');
    const outsider = await t.user('customer');
    const name = photo(offerer, 'f', 'webp');
    await addObject('offer-images', name);
    const exchange = (await t.rpc(owner, 'sf_save_exchange', exchangeArgs())).id;
    await t.rpc(offerer, 'sf_submit_offer', offerArgs(exchange, { p_photo_paths: [name] }));
    assert.equal((await visible(owner, 'offer-images', name)).length, 1);
    assert.deepEqual(await visible(outsider, 'offer-images', name), []);
  });
});
