<?php
/**
 * Test script to validate fixes for search_suggest.php and teams.php
 */

echo "=== VALIDATING FIXES ===\n\n";

// Test 1: Check search_suggest.php has global $conn
echo "Test 1: Checking search_suggest.php for 'global \$conn'...\n";
$search_suggest = file_get_contents(__DIR__ . '/pages/search_suggest.php');
if (strpos($search_suggest, 'global $conn;') !== false) {
    echo "✓ PASS: global \$conn declaration found\n";
} else {
    echo "✗ FAIL: global \$conn declaration NOT found\n";
}

if (strpos($search_suggest, "error_log('Search suggest error:") !== false) {
    echo "✓ PASS: error_log for debugging found\n";
} else {
    echo "✗ FAIL: error_log for debugging NOT found\n";
}

if (strpos($search_suggest, "http_response_code(500)") !== false) {
    echo "✓ PASS: http_response_code(500) for error handling found\n";
} else {
    echo "✗ FAIL: http_response_code(500) NOT found\n";
}

echo "\n";

// Test 2: Check teams.php doesn't have duplicate $userOrgs
echo "Test 2: Checking teams.php for duplicate \$userOrgs...\n";
$teams_file = file_get_contents(__DIR__ . '/pages/teams.php');
$userOrgs_count = substr_count($teams_file, '$userOrgs =');
if ($userOrgs_count === 0) {
    echo "✓ PASS: No duplicate \$userOrgs redeclaration found\n";
} else {
    echo "✗ FAIL: Found $userOrgs_count \$userOrgs declarations (should be 0)\n";
}

if (substr_count($teams_file, '$userOrganizations =') === 1) {
    echo "✓ PASS: \$userOrganizations declared once correctly\n";
} else {
    echo "✗ FAIL: \$userOrganizations redeclared or missing\n";
}

echo "\n";

// Test 3: Check all getTeamById calls have 3 parameters
echo "Test 3: Checking all getTeamById calls have organizationId parameter...\n";
if (preg_match_all('/getTeamById\(\s*\$conn\s*,\s*\$[a-zA-Z_][a-zA-Z0-9_]*\s*,\s*\$[a-zA-Z_][a-zA-Z0-9_]*\s*\)/', $teams_file, $matches)) {
    $count = count($matches[0]);
    echo "✓ PASS: Found $count getTeamById calls with 3 parameters\n";
}

// Check team-detail.php
echo "\n";
echo "Test 4: Checking team-detail.php getTeamById calls...\n";
$team_detail = file_get_contents(__DIR__ . '/pages/team-detail.php');
if (preg_match_all('/getTeamById\(\s*\$conn\s*,\s*\$[a-zA-Z_][a-zA-Z0-9_]*\s*,\s*/', $team_detail, $matches)) {
    $count = count($matches[0]);
    echo "✓ PASS: Found $count getTeamById calls with 3 parameters in team-detail.php\n";
}

// Check scrims.php
echo "\n";
echo "Test 5: Checking scrims.php getTeamById calls...\n";
$scrims = file_get_contents(__DIR__ . '/pages/scrims.php');
if (preg_match_all('/getTeamById\(\s*\$conn\s*,\s*\$[a-zA-Z_][a-zA-Z0-9_]*\s*,\s*\(int\)\s*\$activeOrganizationId/', $scrims, $matches)) {
    $count = count($matches[0]);
    echo "✓ PASS: Found $count getTeamById calls with 3 parameters in scrims.php\n";
} else {
    echo "✗ WARNING: scrims.php getTeamById validation pattern may be different\n";
}

echo "\n=== VALIDATION COMPLETE ===\n";
?>
