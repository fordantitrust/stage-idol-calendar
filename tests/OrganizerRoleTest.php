<?php
/**
 * Organizer role ownership and API guard tests.
 */

require_once __DIR__ . '/../config.php';

function testOrganizerMigrationArtifacts($test) {
    $migration = file_get_contents(__DIR__ . '/../tools/migrate-add-organizer-role.php');
    $artistRequestMigration = file_get_contents(__DIR__ . '/../tools/migrate-add-artist-requests-table.php');
    $setup = file_get_contents(__DIR__ . '/../setup.php');

    $test->assertContains('CREATE TABLE IF NOT EXISTS event_organizers', $migration, 'Migration should create event_organizers');
    $test->assertContains('events.created_by_user_id', $migration, 'Migration should add events.created_by_user_id');
    $test->assertContains('idx_event_organizers_event_id', $migration, 'Migration should create event_id index');
    $test->assertContains('idx_event_organizers_user_id', $migration, 'Migration should create user_id index');
    $test->assertContains('idx_events_created_by_user_id', $migration, 'Migration should create created_by index');

    $test->assertContains('CREATE TABLE IF NOT EXISTS event_organizers', $setup, 'Fresh setup should create event_organizers');
    $test->assertContains('created_by_user_id INTEGER', $setup, 'Fresh setup should include created_by_user_id');
    $test->assertContains('CREATE TABLE IF NOT EXISTS artist_requests', $artistRequestMigration, 'Artist request migration should create artist_requests');
    $test->assertContains('idx_artist_requests_status', $artistRequestMigration, 'Artist request migration should create status index');
    $test->assertContains('idx_artist_requests_created_at', $artistRequestMigration, 'Artist request migration should create created_at index');
    $test->assertContains('idx_artist_requests_requester_user_id', $artistRequestMigration, 'Artist request migration should create requester index');
    $test->assertContains('CREATE TABLE IF NOT EXISTS artist_requests', $setup, 'Fresh setup should create artist_requests');
    $test->assertContains('artist_requests table (Organizer Artist Request system)', $setup, 'Setup checklist should include artist request migration');
}

function testOrganizerApiGuardSurface($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');

    $test->assertContains('Organizer role does not have access to this action', $api, 'API should have organizer deny list');
    $test->assertContains("'event_organizers_list'", $api, 'API should expose assignment list action');
    $test->assertContains("'event_organizers_update'", $api, 'API should expose assignment update action');
    $test->assertContains('requireCanManageProgramIds', $api, 'Bulk program ownership helper should exist');
    $test->assertContains('requireCanManageCreditIds', $api, 'Bulk credit ownership helper should exist');
    $test->assertContains('Organizer programs must be assigned to an event', $api, 'Organizer cannot create global programs');
    $test->assertContains('Organizer credits must be assigned to an event', $api, 'Organizer cannot create global credits');
    $test->assertContains("'events_request_activate'", $api, 'Organizer should be able to submit event activation requests');
    $test->assertContains('function requestActivateEvent', $api, 'Activation request endpoint should exist');
    $test->assertContains('Organizer role cannot activate events', $api, 'Organizer should be blocked from directly activating events');
    $test->assertContains("=== 'activate'", $api, 'Event request approval should support activate requests');
    $test->assertContains('requireApiAdminOrAgentRole', $api, 'Admin or agent role helper should protect event request review');
    $test->assertContains('$requestGroup', $api, 'Event request list should support request group filtering');
    $test->assertContains("request_type != 'activate'", $api, 'Guest event requests should exclude organizer activation requests');
    $test->assertContains("request_type = 'activate'", $api, 'Organizer active requests should be filterable');
    $test->assertContains("'artists_autocomplete'", $api, 'Organizer should be allowed to autocomplete existing artists');
    $test->assertContains("'artists_groups'", $api, 'Organizer should be allowed to load artist groups for request form');
    $test->assertContains("'artist_requests_create'", $api, 'Organizer should be allowed to submit artist requests');
    $test->assertContains("'artist_requests_list'", $api, 'Admin/agent should list artist requests');
    $test->assertContains("'artist_request_approve'", $api, 'Admin/agent should approve artist requests');
    $test->assertContains("'artist_request_reject'", $api, 'Admin/agent should reject artist requests');
    $test->assertContains('function createArtistRequest', $api, 'Artist request create endpoint should exist');
    $test->assertContains('function approveArtistRequest', $api, 'Artist request approve endpoint should exist');
    $test->assertContains('INSERT INTO artists (name, is_group, group_id', $api, 'Artist request approval should create an artists record');
    $test->assertContains("'artist_id' => \$artistId", $api, 'Artist request approval should return the created artist id');
    $test->assertContains('Artist name already exists', $api, 'Artist request duplicate handling should be explicit');
    $test->assertContains('artist_requests_pending', $api, 'Dashboard should include artist request KPI');
    $test->assertContains('event_active_requests_pending', $api, 'Dashboard should include event active request KPI');
    $test->assertContains('missingProgramArtistReferences', $api, 'Organizer program saves should validate artist references');
    $test->assertContains('Organizer can only reference existing artists', $api, 'Organizer should not create new artists through program categories');
    $test->assertContains('bool $allowCreate = true', $api, 'Artist sync should support disabling auto-create');
}

function testOrganizerUiSurface($test) {
    $admin = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../admin/index.php'));
    $i18n = file_get_contents(__DIR__ . '/../admin/js/admin-i18n.js');

    $test->assertContains('value="organizer"', $admin, 'User role dropdown should include organizer');
    $test->assertContains("ADMIN_ROLE === 'organizer'", $admin, 'Admin UI should branch for organizer');
    $test->assertContains('requestActivateEvent(', $admin, 'Organizer UI should expose request active action for inactive events');
    $test->assertContains("conventionIsActive').disabled", $admin, 'Organizer UI should disable direct active checkbox editing');
    $test->assertContains('currentEvReqGroup', $admin, 'Admin UI should separate event request groups');
    $test->assertContains('event_guest', $admin, 'Admin UI should include guest event request menu');
    $test->assertContains('event_active', $admin, 'Admin UI should include organizer active request menu');
    $test->assertContains('artistRequestsSubSection', $admin, 'Admin UI should include Artist Request sub-tab');
    $test->assertContains('artistReqBadge', $admin, 'Admin UI should include Artist Request pending badge');
    $test->assertContains("if (ADMIN_ROLE !== 'organizer') {\n                        loadArtists();", $admin, 'Artist Request approval should refresh admin/agent artist list');
    $test->assertContains('openArtistRequestModal', $admin, 'Organizer UI should open artist modal in request mode');
    $test->assertContains('artistModalMode', $admin, 'Artist modal should support request mode');
    $test->assertFalse(
        strpos($admin, "if (tab === 'artists') loadArtists();") !== false,
        'Organizer Artist tab must not call artists_list through an unconditional loadArtists()'
    );
    $test->assertContains('user.roleOrganizer', $i18n, 'i18n should include organizer role label');
    $test->assertContains('event.requestActive', $i18n, 'i18n should include request active label');
    $test->assertContains('evReq.typeActivate', $i18n, 'i18n should include activate event request type label');
    $test->assertContains('tab.eventGuestRequests', $i18n, 'i18n should include guest event request menu label');
    $test->assertContains('tab.eventActiveRequests', $i18n, 'i18n should include organizer active request menu label');
    $test->assertContains('tab.artistRequests', $i18n, 'i18n should include artist request menu label');
    $test->assertContains('artist.requestTitle', $i18n, 'i18n should include artist request modal title');
    $test->assertContains('modal.artistGroupHintOrganizer', $i18n, 'i18n should explain organizer artist autocomplete-only behavior');
    $test->assertContains('requireSuggestion: ADMIN_ROLE === \'organizer\'', $admin, 'Organizer artist tag input should require autocomplete selection');
}
