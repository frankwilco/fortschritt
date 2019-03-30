<?php
	require_once "database.php";
	require_once "page.php";
	
	function sanitize($data) {
		$data = trim($data);
		$data = stripslashes($data);
		$data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5);
		return $data;
	}
	
	$responses = array(
		"noaction" => "Invalid request: You did not provide an action.",
		"fieldrequirement" => "Your note contains too few characters.",
		"postcreated" => "Your note was posted.",
		"postdeleted" => "Your note was deleted.",
		"postedited" => "Your note was edited.",
		"commentcreated" => "Your comment was posted.",
		"actionfailed" => "Action failed.");
	
	// Default response to return
	$response = $responses["actionfailed"];
	
	// No action eh? No post for you!
	if (!isset($_REQUEST["action"])) {
		$response = $responses["noaction"];
	}

	// Response codes:
	// true = success
	// false = failed
	if (isset($_REQUEST["action"])) {
		$action = $_REQUEST["action"];
		switch ($action) {
			case "new":
				if (isset($_REQUEST["content"]) && isset($_REQUEST["group"])) {
					$content = sanitize($_REQUEST["content"]);
					$groupid = sanitize($_REQUEST["group"]);
					
					if (strlen(trim($content)) < 10) {
						$response = $responses["fieldrequirement"];
						break;
					}
					
					$database = new Database();
					
					$userid = sanitize($page->get_user_id());
					$sectionid = sanitize($database->get_profile_info($userid)[2]);
					
					if ($database->create_post($userid, $groupid, $content, $sectionid) === true) {
						$response = $responses["postcreated"];
					}
				}
				break;
			case "edit":
				if (isset($_REQUEST["content"]) && isset($_REQUEST["post"])) {
					$postid = sanitize($_REQUEST["post"]);
					$content = sanitize($_REQUEST["content"]);
					
					if (strlen(trim($content)) < 10) {
						$response = $responses["fieldrequirement"];
						break;
					}
					
					$database = new Database();
					if ($database->edit_post($postid, $content)) {
						$response = $responses["postedited"];
					}
				}
				break;
			case "delete":
				if (isset($_REQUEST["post"])) {
					$postid = sanitize($_REQUEST["post"]);
					
					$database = new Database();
					if ($database->delete_post($postid)) {
						$response = $responses["postdeleted"];
					}
				}
				break;
			case "get_edit_modal":
				if (isset($_REQUEST["post"])) {
					$response = $page->get_edit_card($_REQUEST["post"]);
				}
				break;
			case "new_comment":
				if (isset($_REQUEST["content"]) && isset($_REQUEST["post"])) {
					$postid = sanitize($_REQUEST["post"]);
					$content = sanitize($_REQUEST["content"]);
					$userid = sanitize($page->get_user_id());
					
					if (strlen(trim($content)) < 10) {
						$response = $responses["fieldrequirement"];
						break;
					}
					
					$database = new Database();
					if ($database->create_comment($userid, $content, $postid)) {
						$response = $responses["commentcreated"];
					}
				}
				break;
			case "get_posts":
				$groupid = empty($_REQUEST['group']) ? null : $_REQUEST['group'];
				$show_category = empty($_REQUEST['show-category']) ? true : $_REQUEST['show-category'];
				$userid = empty($_REQUEST['user']) ? null : $_REQUEST['user'];
				$limit = empty($_REQUEST['limit']) ? 10 : $_REQUEST['limit'];
				$offset = empty($_REQUEST['offset']) ? 0 : $_REQUEST['offset'];
				
				$response = $page->get_user_posts($groupid, true,
							null, $show_category, $userid,
							false, true, $limit, $offset);
				break;
		}
	}

	return print($response);
?>