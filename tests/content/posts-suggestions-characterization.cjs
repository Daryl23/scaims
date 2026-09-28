const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { Phase1Harness, loginAdmin, root } = require('../helpers/phase1-harness.cjs');

const harness = new Phase1Harness('content', 8164);
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4ZkAAAAASUVORK5CYII=', 'base64');

(async () => {
  let orphanedPostPath = null;
  try {
    await harness.start();
    const admin = await harness.newContext();
    assert.equal((await loginAdmin(admin)).status(), 302);

    const marker = `phase1-${harness.database}.png`;
    const create = await admin.post('/ADMIN%20UI/adminDashboard/post/create_post.php', {
      multipart: {
        post_content: 'Phase 1 announcement',
        'post_images[]': { name: marker, mimeType: 'image/png', buffer: png },
      },
      maxRedirects: 0,
    });
    assert.equal(create.status(), 302);
    let state = harness.state();
    assert.equal(state.posts.length, 1);
    const post = state.posts[0];
    assert.equal(post.content, 'Phase 1 announcement');
    const paths = JSON.parse(post.image_paths);
    assert.equal(paths.length, 1);
    orphanedPostPath = path.join(root, paths[0].replaceAll('/', path.sep));
    assert.equal(fs.existsSync(orphanedPostPath), true);
    assert.equal((await admin.get('/ADMIN%20UI/adminDashboard/post/post.php')).status(), 200);

    const edit = await admin.post('/ADMIN%20UI/adminDashboard/post/update_post.php', {
      form: { post_id: String(post.id), post_content: 'Phase 1 announcement\nUpdated' },
      maxRedirects: 0,
    });
    assert.equal(edit.status(), 302);
    state = harness.state();
    assert.equal(state.posts[0].content, 'Phase 1 announcement<br />\nUpdated');

    const remove = await admin.post('/ADMIN%20UI/adminDashboard/post/delete_post.php', {
      form: { post_id: String(post.id) },
    });
    assert.equal((await remove.json()).success, true);
    assert.equal(harness.state().posts.length, 0);
    assert.equal(fs.existsSync(orphanedPostPath), true);
    console.log('KNOWN ISSUE (confirmed): deleting a post removes the database row but leaves its image file.');

    const publicContext = await harness.newContext();
    const suggestionSubmit = await publicContext.post('/submit_suggestion.php', {
      form: { name: 'Phase 1 Visitor', suggestion: 'Please retain this route contract.' },
    });
    assert.equal(suggestionSubmit.status(), 200);
    assert.equal((await suggestionSubmit.json()).success, true);
    state = harness.state();
    assert.equal(state.suggestions.length, 1);
    assert.equal(state.suggestions[0].status, 'new');
    const suggestionId = state.suggestions[0].id;

    const newList = await admin.get('/ADMIN%20UI/adminDashboard/suggestions/get_suggestions.php?type=new');
    const newRows = await newList.json();
    assert.equal(newRows.some(row => Number(row.id) === Number(suggestionId)), true);

    const markRead = await admin.post('/ADMIN%20UI/adminDashboard/suggestions/update_suggestion.php', {
      headers: { 'Content-Type': 'application/json' },
      data: { id: suggestionId, action: 'mark_read' },
    });
    assert.equal((await markRead.json()).success, true);
    assert.equal(harness.state().suggestions[0].status, 'read');
    const previousRows = await (await admin.get('/ADMIN%20UI/adminDashboard/suggestions/get_suggestions.php?type=previous')).json();
    assert.equal(previousRows.some(row => Number(row.id) === Number(suggestionId)), true);

    const deleteSuggestion = await admin.post('/ADMIN%20UI/adminDashboard/suggestions/update_suggestion.php', {
      headers: { 'Content-Type': 'application/json' },
      data: { id: suggestionId, action: 'delete' },
    });
    assert.equal((await deleteSuggestion.json()).success, true);
    assert.equal(harness.state().suggestions.length, 0);

    console.log('PASS: Admin post create/list/edit/delete and canonical public/Admin suggestion lifecycle.');
  } finally {
    const cleanup = await harness.stop();
    console.log(`CLEANUP: ${cleanup}`);
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
