<?php

require_once "database.php";
require_once "components/parsedown/parsedown.php";

$page = new Page();

class Page {
	private $messages = null;
	
	function __construct() {
		$this->messages = array(
			"notfound" => "No more notes.",
			"nocomments" => "No comments.",
			"nomaterials" => "No materials available.",
			"notfoundmaterial" => "Material not found.");
	}
	
	function get_user_id() {
		$cookie_name = 'userid';
		if(!isset($_COOKIE[$cookie_name])) {
			// Default user...
			return 0;
		}
		return $_COOKIE[$cookie_name];
	}
	function get_message_card($message) {
		echo '<div class="card" id="Card-NotFound">';
		echo '<div class="card-notfound">';
		echo $message;
		echo '</div>';
		echo '</div>';
	}
	function get_moreposts_card($where) {
		echo '<div class="card" id="Card-MoreNotes">';
		printf('<div class="card-notfound" onclick="Fortscript.addPosts(\'%s\');">', $where);
		echo 'Get more notes';
		echo '</div>';
		echo '</div>';		
	}
	function get_user_card($userid) {
		$database = new Database();
		$row = $database->get_profile_info($userid);
		
		// Profile picture
		echo '<div class="mr">';
		$this->get_user_profile_picture($row[1], $row[0]);
		echo '</div>';
		
		// User information
		echo '<div class="profile-information flex-container column">';
		// Full Name
		echo '<span class="profile-username">';
		echo $row[1];
		echo '</span>';
		// Grade and Section
		echo '<span class="subtitle">';
		printf('Grade %s', $database->get_section_displayname($row[2]));
		$this->get_access_level($row[7]);
		echo '</span>';
		echo '</div>';
	}
	function get_access_level($accessid) {
		echo '<div class="flex-container align-center subtitle bold">';
		switch ($accessid) {
			case 0:
				echo '<span class="material-icons md-18">person</span>';
				echo 'Student';
				break;
			case 1:
				echo '<span class="material-icons md-18">school</span>';
				echo 'Teacher';
				break;
			case 2:
				echo '<span class="material-icons md-18">school</span>';
				echo 'Principal';
				break;
		}
		echo '</div>';
	}
	function get_user_groups($userid) {
		$database = new Database();
		$profile_info = $database->get_profile_info($userid);
		
		$result = $database->get_joined_groups($profile_info[3]);
		while ($row = $result->fetch_row()) {
			printf('<a href="groups?id=%s">', $row[0]);
			echo '<li>';
			echo $row[1];
			echo '</li>';
			echo '</a>';
		}
	}
	function get_group_name($groupid = null) {
		$database = new Database();
		$group = $database->get_group_displayname($groupid);
		if (isset($group)) {
			echo $group;
		}
	}
	function get_user_posts($groupid = null, $has_card = true, $postid = null,
							$show_category = true, $userid = null,
							$is_story = false, $sort_bydate = true, $limit = 10, $offset = 0) {
		$database = new Database();
		$sectionid = $database->get_profile_info($this->get_user_id())[2];
		$result = $database->get_posts($groupid, $sectionid, $postid, $userid, $sort_bydate, $limit, $offset);

		if (!isset($result) || (isset($postid) && $postid == 0)) {
			$this->get_message_card($this->messages["notfound"]);
			return;
		}

		// $row[0] = post ID
		// $row[1] = post date
		// $row[2] = post content
		// $row[3] = user ID
		// $row[4] = user section
		// $row[5] = user full name
		// $row[6] = user profile picture
		// $row[7] = user access level
		// $row[8] = group name where post is located
		while ($row = $result->fetch_row()) {
			if ($has_card) {
				echo '<div class="card">';
			}
			echo '<div class="card-post">';
			
			printf('<div class="mr"><a href="profile?id=%s">', $row[3]);
				$this->get_user_profile_picture($row[5], $row[3]);
			echo '</a></div>';
			
			echo '<div class="post-content">';
			echo '<div class="post-header flex-container align-start justify-sb">';
			
			echo '<div class="post-header-information flex-container align-center">';
			printf('<span class="profile-username mr"><a href="profile?id=%s">%s</a></span>', $row[3], $row[5]);
			$this->get_access_level($row[7]);
			if ($show_category) {
				printf('<span class="post-category ml">to %s</span>', $row[8]);
			}
			echo '</div>';
			
			if ($this->get_user_id() == $row[3]) {
				printf('<div class="post-header-controls" postid="%s">', $row[0]);
				echo '<div class="button no-padding material-icons md-18 delete-post" onclick="Fortscript.deletePost(event)">delete_outline</div>';
				echo '<div class="button no-padding material-icons md-18 edit-post" onclick="Fortscript.editPost(event)">edit</div>';
				echo '</div>';
			}
			echo '</div>';
			
			echo '<div class="largetitle">';
			echo nl2br($row[2]);
			echo '</div>';
			echo '</div>';
			
			echo '</div>';
			
			echo '<div class="card-footer subtitle flex-container no-padding">';
			
			if (!$is_story) {
				echo '<div class="post-controls">';
				echo '<div class="button flex-container align-center">';
				echo '<span class="material-icons md-18 mr">comment</span>';
				printf('<a href="story.php?id=%s">Comments</a>', $row[0]);
				echo '</div>';
				echo '</div>';
			}
			
			echo '<div class="post-datetime flex-container align-center justify-fe">';
			printf("Posted on: %s", date("Y-m-d", strtotime($row[1])));
			echo '</div>';
			
			echo '</div>';
			
			if ($has_card) {
				echo '</div>';
			}
			if ($is_story) {
				$this->get_newcomment_card();
				$this->get_post_comments($row[0]);
			}
		}
	}
	// TODO: Implement proper ajax for progressive posts
	function get_post_comments($postid, $limit = 999999, $offset = 0) {
		$database = new Database();
		$result = $database->get_comments($postid, $limit, $offset);

		if (!isset($result)) {
			$this->get_message_card($this->messages["nocomments"]);
			return;
		}

		// $row[0] = comment ID
		// $row[1] = parent post ID
		// $row[2] = comment content
		// $row[3] = comment date
		// $row[4] = user ID
		// $row[5] = user full name
		// $row[6] = user profile picture
		// $row[7] = user access level
		echo '<div class="card">';
		echo '<div class="card-header">';
		echo 'Comments';
		echo '</div>';
		while ($row = $result->fetch_row()) {
			echo '<div class="card-post">';
			
				printf('<div class="mr"><a href="profile?id=%s">', $row[4]);
					$this->get_user_profile_picture($row[5], $row[4]);
				echo '</a></div>';
				
				echo '<div class="post-content">';
					echo '<div class="post-header flex-container align-start justify-sb">';
						echo '<div class="post-header-information flex-container align-center">';
						printf('<span class="profile-username mr"><a href="profile?id=%s">%s</a></span>', $row[4], $row[5]);
						$this->get_access_level($row[7]);
						echo '</div>';
						
						if ($this->get_user_id() == $row[4]) {
							printf('<div class="post-header-controls" commentid="%s">', $row[0]);
							echo '<div class="button no-padding material-icons md-18 delete-post" onclick="Fortscript.deleteComment(event)">delete_outline</div>';
							// echo '<div class="button no-padding material-icons md-18 edit-post" onclick="Fortscript.editPost(event)">edit</div>';
							echo '</div>';
						}
					echo '</div>';
					
					echo '<div class="largetitle">';
						echo nl2br($row[2]);
					echo '</div>';
				echo '</div>';
			
			echo '</div>';
			
			echo '<div class="card-footer subtitle flex-container no-padding">';			
			echo '<div class="post-datetime flex-container align-center justify-fe">';
			printf("Posted on: %s", date("Y-m-d", strtotime($row[3])));
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
	}
	function get_user_profile_picture($name, $userid) {
		echo '<div class="profile-picture flex-container">';
		$profile_image = sprintf('profiles/%s.jpg', $userid);
		if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $profile_image)) {
			printf('<img src="%s"/>', $profile_image);
		} else {
			echo substr($name, 0, 1);
		}
		echo '</div>';
	}
	function get_profile_card($userid) {
		printf('<div class="card profile-cover" style="background: rgb(150, 150, 150) url(\'profiles/%s_cover.jpg\'); background-size: cover;">', $userid);
		echo '<div class="flex-container profile">';
		$this->get_user_card($userid);
		echo '</div>';
		echo '</div>';
	}
	function get_section_options() {
		$database = new Database();
		$result = $database->get_sections();
		// $row[0] = section ID
		// $row[1] = display name
		if ($result->num_rows > 0) {
			while ($row = $result->fetch_row()) {
				printf('<option value="%s">%s</option>', $row[0], $row[1]);
			}
		}
	}
	function get_group_options() {
		$database = new Database();
		$profile_info = $database->get_profile_info($this->get_user_id());
		$result = $database->get_joined_groups($profile_info[3]);
		// $row[0] = group ID
		// $row[1] = display name
		// $row[2] = admins ID
		if ($result->num_rows > 0) {
			while ($row = $result->fetch_row()) {
				printf('<option value="%s">%s</option>', $row[0], $row[1]);
			}
		}
	}
	function get_newcomment_card() {
		echo '<div class="card">';
		echo '<div class="card-header">';
		echo 'Leave a comment';
		echo '</div>';
		echo '<div class="card-content flex-container column">';
		echo '<textarea id="Comment-area" placeholder="Type your comment here..."></textarea>';
		echo '<div class="flex-container mt">';
		echo '<input id="Comment-send" type="submit" name="submit" value="Post"/>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}
	function get_newpost_card() {
		echo '<div class="card">';
		echo '<div class="card-header">';
		echo 'New Note';
		echo '</div>';
		echo '<div class="card-content flex-container column">';
		echo '<textarea id="Post-area" placeholder="Type your note here..."></textarea>';
		echo '<div class="flex-container mt">';
		echo '<span class="self-center mr">Group:</span>';
		echo '<select id="GroupSelector-Menu" class="mr">';
		$this->get_group_options();
		echo '</select>';
		echo '<input id="Post-send" type="submit" name="submit" value="Post"/>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}
	function get_edit_card($postid) {
		$database = new Database();
		$content = $database->get_posts(null, null, $postid)->fetch_row()[2];
		echo '<div class="card">';
		echo '<div class="card-header">';
		echo 'Edit Note';
		echo '</div>';
		echo '<div class="card-content flex-container column">';
		printf('<textarea id="Edit-area" placeholder="Type your note here...">%s</textarea>', $content);
		echo '<input id="Edit-send" type="submit" name="submit" value="Edit" onclick="Fortscript.sendEditedPost();"/>';
		echo '</div>';
		echo '</div>';
	}

	function get_material_types_card($groupid) {
		$database = new Database();
		$result = $database->get_material_types();

		if (!isset($result)) {
			return;
		}
		
		// $row[0] = type ID
		// $row[1] = display name
		echo '<div class="card min-padding flex-container">';
		echo '<div class="card-header no-border mr">';
		echo 'Filter by:';
		echo '</div>';
		echo '<ul class="sidebar-navigation no-border flex-container">';
		while ($row = $result->fetch_row()) {
			printf('<li><a href="materials?group=%s&type=%s">%s', $groupid, $row[0], $row[1]);
			echo '</a></li>';
		}
		echo '</ul>';
		echo '</div>';
	}
	function get_material_groups_card($typeid) {
		$database = new Database();
		$profile_info = $database->get_profile_info($this->get_user_id());
		$result = $database->get_joined_groups($profile_info[3]);
		
		while ($row = $result->fetch_row()) {
			printf('<a href="materials?group=%s&type=%s">', $row[0], $typeid);
			echo '<li>';
			echo $row[1];
			echo '</li>';
			echo '</a>';
		}
	}
	function get_material_list_card($typeid, $groupid) {
		$database = new Database();
		$result = $database->get_materials($typeid, $groupid, $this->get_user_id());
		
		if (!isset($result)) {
			$this->get_message_card($this->messages["nomaterials"]);
			return;
		}
		
		echo '<div class="card">';
		
		echo '<div class="card-header">';
		echo $this->get_group_name($groupid);
		echo '</div>';
		
		echo '<div class="card-post">';
		echo '<div class="post-content">';
		echo '<ul class="sidebar-navigation flex-container column">';
		// $row[0] = material ID
		// $row[1] = material type ID
		// $row[2] = material group ID
		// $row[3] = material display name
		// $row[4] = material description
		// $row[5] = grade level where material should be visible
		// $row[6] = material file name on server
		while ($row = $result->fetch_row()) {
			printf('<a href="materials?id=%s"><li>%s</li></a>', $row[0], $row[3]);
		}
		echo '</ul>';
		echo '</div>';
		echo '</div>';
		
		echo '</div>';
	}
	function get_material_view_card($materialid) {
		$database = new Database();
		$parsedown = new Parsedown();
		$parsedown->setSafeMode(true);
		$result = $database->get_materials(null, null, $this->get_user_id(), $materialid);
		
		if (!isset($result)) {
			$this->get_message_card($this->messages["notfoundmaterial"]);
			return;
		}
		
		echo '<div class="card">';
		
		// $row[0] = material ID
		// $row[1] = material type ID
		// $row[2] = material group ID
		// $row[3] = material display name
		// $row[4] = material description
		// $row[5] = grade level where material should be visible
		// $row[6] = material file name on server
		while ($row = $result->fetch_row()) {
			echo '<div class="card-post">';
			echo '<div class="post-content flex-container column">';
			printf('<span class="material-title self-center">%s</span>', $row[3]);
			echo '<span class="material-subject self-center">';
			echo $this->get_group_name($row[2]);
			echo '</span>';
			if (strlen(trim($row[6])) > 0) {
				printf('<a href="files/%s">', $row[6]);
				echo '<div class="button has-border">';
				echo '<span class="material-icons mr">open_in_browser</span>';
				echo 'View file';
				echo '</div></a>';
			}
			// Check if we need description
			if (strlen(trim($row[4])) > 0) {
				// TODO: Consider using markdown formatting
				echo '<div class="material-content">';
				echo $parsedown->text($row[4]);
				echo '</div>';
			}
			echo '</div>';
			echo '</div>';
		}
		
		echo '</div>';
	}
}

?>