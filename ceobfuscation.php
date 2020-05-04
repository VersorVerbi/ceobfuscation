<?php
/* * * * * * * * * * * * * * * * * * * * * * * *
Plugin Name: Complete Email Obfuscation
Plugin URI: http://www.versorbooks.com/
Description: Complete Email Obfuscation will convert existing mailto: links to WordPress page links that will still prompt the user's email client (like a mailto: link) while hiding the address from email scrapers.
Version: 1.0
Author Name: Nathaniel Turner
Author URI: http://www.versorbooks.com/
 * * * * * * * * * * * * * * * * * * * * * * * *
**/

///////////////////////////////////////////////////////////////////////////////
////////////////////////////// SETUP FUNCTIONS ////////////////////////////////
///////////////////////////////////////////////////////////////////////////////
/* --- ceobfuscate SUBROUTINE --- */
/* CALLED BY: [main]
   DESCRIPTION:
    Creates the CEO settings page under the Settings menu in WP Admin */
function ceobfuscate() {
	global $ceoadminpage;
    $ceoadminpage = add_options_page('Email Obfuscation Settings',
					 'Complete Email Obfuscation','manage_options',
					 'emailobfuscation-settings','ceobfuscate_options');
}

/* --- ceobfuscate_options SUBROUTINE --- */
/* CALLED BY: ceobfuscate
   DESCRIPTION:
    Generates the actual settings page */
function ceobfuscate_options() {
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.'));
	}
    
    add_thickbox();
    
    $ceoPrevent = get_option('ceopreventpublish','n');
    $ceoContent = get_option('ceocontent','nm');
    
    ?>

<div class="wrap">
    <!-- Header -->
    <div id="icon-plugins" class="icon32"></div>
    <h2>Complete Email Obfuscation Settings</h2>

<?php
    if (isset($_GET['success'])) {
        $yayCount = $_GET['success'];
        if ($yayCount > 0) {
            echo '<div class="notice notice-success">' . $yayCount . ($yayCount > 1 ? ' recipients were' : ' recipient was') . ' successfully updated.</div>';
        } elseif ($yayCount === FALSE) {
            echo '<div class="notice notice-error">Something went wrong. Please try again.</div>';
        } else {
            echo '<div class="notice notice-info">No new records were added (there was nothing new to add).</div>';
        }
    } elseif (isset($_GET['deleted'])) {
        $booCount = $_GET['deleted'];
        if ($booCount > 0) {
            echo '<div class="notice notice-success">' . $booCount . ($booCount > 1 ? ' recipients were' : ' recipient was') . ' successfully deleted.</div>';
        } else {
            echo '<div class="notice notice-error">Something went wrong. Please try again.</div>';
        }
    } elseif ($_POST['formSubmitted'] == 1) {
        $successfulUpdate = true;
        if (isset($_POST['ceo_preventpub'])) {
            $successfulUpdate = $successfulUpdate && update_option('ceopreventpublish',$_POST['ceo_preventpub']);
        }
        if (isset($_POST['ceo_content'])) {
            $successfulUpdate = $successfulUpdate && update_option('ceocontent',$_POST['ceo_content']);
        }
        if ($successfulUpdate) {
            echo '<div class="notice notice-success">Settings have been saved!</div>';
        } elseif ($successfulUpdate === false) {
            echo '<div class="notice notice-error">Something went wrong. Please try again.</div>';
        }
    }
?>
    <form name="form1" method="post" action="">
        <table class="form-table">
            <tbody>
                <tr>
                    <td colspan=2>
                        <button class="button-secondary" id="ceoimportusers" onclick="ceo_import_users()">Import WP Users</button>
                        <button class="button-secondary" id="ceoimportemails" onclick="ceo_import_emails()">Import Existing Email Links</button>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        Prevent publishing mailto links? <span id="ceo_preventpubhelp" class="ceohelp" onclick="ceoShowHelp(this)">?</span>
                    </th>
                    <td>
                        <select name="ceo_preventpub">
                            <option <?php echo ($ceoPrevent == 'n' ? "selected" : "") ?> value="n">No</option>
                            <option <?php echo ($ceoPrevent == 'y' ? "selected" : "") ?> value="y">Yes</option>
                        </select>
                        <div class="ceo-hidden-help hidden description">If this setting is enabled, the system will show a notice alerting you to the presence of a mailto link in your post or page. You will have the option to automatically switch to an obfuscated link or to publish anyway. If this setting is disabled, the system will not prevent you from publishing new mailto links that are not obfuscated.<div style="background: #fff; border-left: 4px solid #ffb900; box-shadow: 0px 1px 1px 0px rgba(0,0,0,0.1); padding: 1px 12px; margin-top: 4px;"><p style="margin: 0px;">Warning! This functionality currently only works with the classic editor. In order to keep using this, you will need the classic editor plugin when Gutenberg is rolled out officially.</p></div></div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        Default link content: <span id="ceo_contenthelp" class="ceohelp" onclick="ceoShowHelp(this)">?</span>
                    </th>
                    <td>
                        <select name="ceo_content">
                            <option <?php echo ($ceoContent == 'nm' ? "selected" : "") ?> value="nm">Name</option>
                            <option <?php echo ($ceoContent == 'full' ? "selected" : "") ?> value="full">Full Name (Prefix, Name, Suffix)</option>
                            <option <?php echo ($ceoContent == 'eml' ? "selected" : "") ?> value="eml">Email</option>
                            <option <?php echo ($ceoContent == 'empty' ? "selected" : "") ?> value="empty">Empty</option>
                        </select>
                        <div class="ceo-hidden-help hidden description">When adding new obfuscated email links to your posts or pages, what you select here will determine which field we automatically use as the displayed text for the link. By default, we use the Name of the recipient. If the selected value does not exist, we will display the email address by default; on the other hand, if you select Empty, we will never put any text in the link by default.</div>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <input type="hidden" name="formSubmitted" value="1" />
            <input type="submit" class="button-primary" title="Save Settings" value="Save Settings" />
        </p>
    </form>
    <div id="icon-users" class="icon32"></div>
    <h2>Existing Email Recipients</h2>
    <table class="widefat">
        <thead>
            <tr>
                <td>ID</td>
                <td>Email</td>
                <td>Prefix</td>
                <td>Name</td>
                <td>Suffix</td>
                <td></td>
            </tr>
        </thead>
        <tbody>
    <?php
        global $wpdb;
        $tableName = $wpdb->prefix . "ceobfuscationrecords";
        $allEmailRecipients = $wpdb->get_results("SELECT * FROM $tableName;");
        foreach ($allEmailRecipients as $row) {
            echo '<tr>';
            echo '<td>' . $row->id . '</td>';
            echo '<td>' . $row->email . '</td>';
            echo '<td>' . $row->prefix . '</td>';
            echo '<td>' . $row->name . '</td>';
            echo '<td>' . $row->suffix . '</td>';
            echo '<td><a class="thickbox button ceo-edit-recip" href="#TB_inline?&width=600&height=350&inlineId=edit-recipient" title="Edit Email Recipient" onclick="loadRecipientEditor(' . $row->id . ')">Edit</a></td>';
            echo '</tr>';
        }
    ?>
        </tbody>
    </table>
</div>

    <?php
}

/* --- add_ceo_action_links SUBROUTINE --- */
/* CALLED BY: [main]
   PARAMETERS:
    $links  - the existing links for the plugin
   DESCRIPTION:
    Adds a link to the CEO settings page on the Plugins page */
function add_ceo_action_links( $links ) {
  $mylinks = array(
	'<a href="' . admin_url( 'options-general.php?page=emailobfuscation-settings' ) .
	'">Settings</a>',
  );
  return array_merge( $links, $mylinks );
}

/* --- add_ceo_row_meta SUBROUTINE --- */
/* CALLED BY: [main]
   PARAMETERS:
    $links  - the existing links for the plugin
    $file   - the file associated with the plugin
   DESCRIPTION:
    Adds several relevant details to the Plugins page */
function add_ceo_row_meta($links, $file) {
	if (strpos($file,'ceobfuscation.php') !== false) {
		$new_links = array(
						'by' => 'By <a href="http://www.versorbooks.com">' .
								'Nathaniel Turner</a>',
						'doc' => '<a href="../wp-content/plugins/' .
								'ceobfuscation/readme.txt">' .
								'Documentation</a>'
					);
		$links = array_merge($links,$new_links);
	}
	return $links;
}

/* --- create_ceo_db SUBROUTINE --- */
/* CALLED BY: [main]
   DESCRIPTION:
    Creates the CEO table in the database. */
function create_ceo_db() {
	global $wpdb;
	$tableName = $wpdb->prefix . "ceobfuscationrecords";
	$charsetCollate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE $tableName (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		email varchar(55) NOT NULL,
		prefix tinytext DEFAULT '' NOT NULL,
		name tinytext DEFAULT '' NOT NULL,
		suffix tinytext DEFAULT '' NOT NULL,
		PRIMARY KEY (id)
	) $charsetCollate ENGINE=InnoDB;";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

/* --- ceo_media_button SUBROUTINE --- */
/* CALLED BY: [main]
   DESCRIPTION:
    Creates the media button when editing pages/posts. */
function ceo_media_button() {
    global $pagenow, $wp_version;
    $output = '';
    if ( version_compare( $wp_version, '3.5', '>=' ) AND in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'post-edit.php' ) ) ) {
        //$img = '<style>#ceo-button::before { font: 400 18px/1 dashicons; content: \'\f003\'; }</style><span class="wp-media-buttons-icon" id="ceo-button"></span>';
        $output = '<a href="#TB_inline?&width=350&height=300&inlineId=add-email-link" id="ceo_add_button" class="thickbox button" title="Add Email Link" style="padding-left: .4em;" onclick="watchForMutationThenAct();"> ' . $img . 'Add Email Link</a>';
    }
    echo $output;
}

/* --- ceo_admin_footer SUBROUTINE --- */
/* CALLED BY: [main]
   CALLS:
    JS function ceo_autocomplete()
    JS function loadRecipients()
   DESCRIPTION:
    Creates the thickbox content for the media button to function. */
function ceo_admin_footer() {
    global $pagenow, $wp_version;
    
    if ( version_compare( $wp_version, '3.5', '>=' ) AND in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'post-edit.php' ) ) ) {
        $ceoContent = get_option('ceocontent','nm');
        echo "<script type='text/javascript'>var ceoContent = '$ceoContent';</script>";
?>
<div id="add-email-link" style="display:none;">
    <form name="ceoaddtopost" id="ceoaddtopost" method="post" action="" autocomplete="off">
        <div class="ceospinner"></div>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">Email:</th>
                    <td>
                        <div id="ceoSearchContainer">
                            <input name="email" id="ceoSearch" type="text" placeholder="Recipient's email address" />
                            <div id="ceoSearchItems"></div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Display text:</th>
                    <td>
                        <input name="content" id="ceoLinkContent" type="text" placeholder="Text that the viewer sees" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Link CSS Class:</th>
                    <td>
                        <input name="linkClass" id="ceoLinkClass" type="text" placeholder="CSS class for your anchor tag" />
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <input type="hidden" id="ceoIdResult" name="ceoIdResult" />
            <button type="button" class="button-primary" onclick="addLinkToText();" title="Insert">Insert</button>
            <button type="button" class="button-secondary" onclick="tb_remove();" title="Cancel">Cancel</button>
        </p>
    </form>
</div>
<script type="text/javascript">ceo_autocomplete(); loadRecipients();</script>
<?php
    }
}

/* --- ceo_edit_footer SUBROUTINE --- */
/* CALLED BY: [main]
   DESCRIPTION:
    Creates the thickbox content for the settings page edit buttons to function. */
function ceo_edit_footer() {
    global $ceoadminpage;
    $screen = get_current_screen();
    if ( $screen->id == $ceoadminpage ) {
?>
<div id="edit-recipient" style="display:none;">
    <form name="ceoeditrecipient" id="ceoeditrecipient" method="post" action="">
        <div class="ceospinner"></div>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">Email Address:</th>
                    <td><input type="email" name="ceoemail" id="ceoemail" /></td>
                </tr>
                <tr>
                    <th scope="row">Name Prefix:</th>
                    <td>
                        <select id="ceoprefix" name="ceoprefix">
                            <option></option>
                            <option>Mr.</option>
                            <option>Mrs.</option>
                            <option>Miss</option>
                            <option>Ms.</option>
                            <option>Dr.</option>
                            <option>Fr.</option>
                            <option>Br.</option>
                            <option>Sr.</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Recipient's Name:</th>
                    <td><input type="text" name="ceoname" id="ceoname" /></td>
                </tr>
                <tr>
                    <th scope="row">Name Suffix:</th>
                    <td><input type="text" name="ceosuffix" id="ceosuffix" /></td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <input type="hidden" id="ceoid" name="ceoid" />
            <button type="button" class="button-primary" onclick="saveRecipient();" title="Save">Save</button>
            <button type="button" class="button-secondary" onclick="tb_remove();" title="Cancel">Cancel</button>
            <button type="button" class="ceo-button-delete" onclick="deleteRecipient()" title="Delete this recipient; doing so will break any existing links for this recipient">Delete</button>
        </p>
    </form>
</div>
<?php
    }
}

/* --- ceo_admin_notice SUBROUTINE --- */
/* CALLED BY: [main]
   DESCRIPTION:
    Adds a warning notice to the post-edit page for notifying end users that a 
    post contains email addresses which have not been obfuscated. */
function ceo_admin_notice() {
    global $pagenow;
    $ceoPrevent = get_option('ceopreventpublish','n');
    if ( ( $pagenow == 'post.php' || $pagenow == 'post-new.php' ) && ( $ceoPrevent != 'n' ) ) {
        echo '<div class="notice notice-warning hidden is-dismissible" id="ceo_notice"><p>Your post has traditional email links that may be picked up by spam bots. Click <a href="javascript:;" onclick="updateOpenPost()">here</a> to change those links to obfuscated email links before posting, or save the post again to ignore this message.</p></div>';
    }
}
///////////////////////////////////////////////////////////////////////////////


///////////////////////////////////////////////////////////////////////////////
//////////////////////////////// AJAX FUNCTIONS ///////////////////////////////
///////////////////////////////////////////////////////////////////////////////

/* --- ceoemailload FUNCTION --- */
/* CALLED BY: JS function loadRecipients()
   RETURNS: JSON string of all existing recipient records
   DESCRIPTION:
    Queries plugin table for all existing recipient data. */
function ceoemailload() {
    global $wpdb;
    $tableName = $wpdb->prefix . "ceobfuscationrecords";

    $possibilities = $wpdb->get_results("SELECT * FROM $tableName ORDER BY email;");
    echo json_encode($possibilities);
    
    wp_die();
}

/* --- ceogetid FUNCTION --- */
/* CALLED BY: JS function returnEmailRecipientId()
   INPUT VARIABLES:
    s   - the email string to search for/create
   RETURNS: Int-32 recipient ID number
   DESCRIPTION:
    Queries plugin table for existing recipient ID; if none found, creates the recipient and returns new ID */
function ceogetid() {
    global $wpdb;
    $tableName = $wpdb->prefix . "ceobfuscationrecords";

    // Get search string from GET params
    $str = $_POST['s'];

    if (strlen($str) > 0) {
        $recipient = $wpdb->get_row("SELECT id FROM $tableName WHERE email LIKE '$str';");
        if (count($recipient) == 0) {
            $wpdb->insert( $tableName, array( 'email' => $str ) );
            $recipientId = $wpdb->insert_id;
        } else {
            $recipientId = $recipient->id;
        }
        echo $recipientId;
    }
    
    wp_die();
}

/* --- ceoemailload FUNCTION --- */
/* CALLED BY: JS function loadRecipients()
   INPUT VARIABLES:
    u   - recipient ID number
   RETURNS: recipient email address
   DESCRIPTION:
    Retrieves recipient email address based on recipient ID */
function ceosendemail() {
    global $wpdb;

    /* --- invalidID FUNCTION --- */
    /* RETURNS: 1 if the ID is invalid, FALSE otherwise
       Checks whether a given ID string is a valid ID */
    function invalidID($recipID) {
        $regex = "/\D+/"; // only numbers are allowed in IDs, so check for anything else
        return preg_match($regex,$recipID);
    }

    // retrieve recipient ID from POST params
    $recipID = $_POST['u'];
    
    // Make sure someone didn't sneak their way to this page with bad data
    if (invalidID($recipID)) {
        echo "";
        wp_die();
    }

    // Retrieve email link from the database
    $tableName = $wpdb->prefix . "ceobfuscationrecords";
    $recipient = $wpdb->get_row("SELECT email FROM $tableName WHERE id=$recipID;");
    $recipientEmail = $recipient->email;
    echo $recipientEmail;
    
    wp_die();
}

/* --- ceoeditrecipient FUNCTION --- */
/* CALLED BY: JS function loadRecipientEditor()
   INPUT VARIABLES:
    u   - recipient ID number
   RETURNS: JSON string from an array of recipient data
   DESCRIPTION:
    Queries plugin table for recipient data based on given recipient ID. */
function ceoeditrecipient() {
    global $wpdb;
    
    // retrieve edited recipient ID from POST params
    $recipID = $_POST['u'];
    
    if ($recipID > 0) {
        $tableName = $wpdb->prefix . "ceobfuscationrecords";
        $recipient = $wpdb->get_row("SELECT * FROM $tableName WHERE id=$recipID;");
        $name = $recipient->name;
        $email = $recipient->email;
        $prefix = $recipient->prefix;
        $suffix = $recipient->suffix;
    } else {
        $name = '';
        $email ='';
        $prefix = '';
        $suffix = '';
    }
    
    $output = array( 'id' => $recipID, 'email' => $email, 'name' => $name, 'prefix' => $prefix, 'suffix' => $suffix );
    echo json_encode( $output );
    
    wp_die();
}

/* --- ceosaverecipient FUNCTION --- */
/* CALLED BY: JS function saveRecipient()
   INPUT VARIABLES:
    id      - recipient ID number
    email   - (new) recipient email
    name    - (new) recipient name
    prefix  - (new) recipient prefix
    suffix  - (new) recipient suffix
   RETURNS: TRUE
   DESCRIPTION:
    Saves recipient information to the database. */
function ceosaverecipient() {
    global $wpdb;
    
    // prepare to update the table
    $tableName = $wpdb->prefix . "ceobfuscationrecords";
    
    // retrieve new values from POST
    $id = $_POST['id'];
    $email = $_POST['email'];
    $name = $_POST['name'];
    $prefix = $_POST['prefix'];
    $suffix = $_POST['suffix'];
    
    $updates = array( 'email' => $email, 'name' => $name, 'prefix' => $prefix, 'suffix' => $suffix );
    $where = array( 'id' => $id );
    wp_localize_script( 'ceo-js', 'UPDATESUCCESS', $wpdb->update( $tableName, $updates, $where ) );
    echo TRUE;
    
    wp_die();
}

/* --- ceo_importusers SUBROUTINE --- */
/* CALLED BY: JS function ceo_import_users()
   DESCRIPTION:
    Retrieves WordPress user data and creates recipient records for each of them. */
function ceo_importusers() {
    global $wpdb;

    $userTable = $wpdb->prefix . "users";
    $metaTable = $wpdb->prefix . "usermeta";
    $ourTable = $wpdb->prefix . "ceobfuscationrecords";

    $sql = "SELECT $userTable.user_email AS eml,
                   GROUP_CONCAT($metaTable.meta_value) AS nameValue
            FROM $userTable INNER JOIN $metaTable
                ON $userTable.ID = $metaTable.user_id
            WHERE $metaTable.meta_key IN ('first_name','last_name')
            GROUP BY eml;";

    $allUserEmailsAndNames = $wpdb->get_results($sql);
    
    $success = true;
    $count = 0;

    foreach ($allUserEmailsAndNames as $user) {
        if ( $wpdb->get_var( "SELECT COUNT(*) FROM $ourTable WHERE email = '" . $user->eml . "';" ) > 0 ) {
            continue;
        }
        $inserts = array( 'email' => $user->eml, 'name' => str_replace(',', ' ', $user->nameValue) );
        $success = $success && $wpdb->insert( $ourTable, $inserts );
        if ($success === FALSE) {
            echo "false";
            break;
        } else {
            $count++;
        }
    }
    if ($success === FALSE) {
        echo -1;
    } else {
        echo $count;
    }
    wp_die();
}

/* --- ceo_importlinks SUBROUTINE --- */
/* CALLED BY: JS function ceo_import_emails()
   CALLS:
    rightpad()
    addToLengthValues()
   DESCRIPTION:
    Retrieves email links from the WordPress posts database, creates recipient
    records for each of them, and converts the existing links to recipient links. */
function ceo_importlinks() {
    global $wpdb;
    
    $postTable = $wpdb->prefix . "posts";
    $ourTable = $wpdb->prefix . "ceobfuscationrecords";
    $additions = 0;
    
    $regex = "mailto:[a-zA-Z0-9]+@[a-zA-Z0-9]+\.[a-zA-Z.]{2,5}";
    
    $sql = "SELECT ID,post_content FROM $postTable WHERE post_content REGEXP '$regex' AND post_status = 'publish';";
    
    $posts = $wpdb->get_results($sql);
    
    $linksToChange = [];
    
    foreach($posts as $post) {
        $mailtoPos = strpos($post->post_content, 'mailto:');
        while($mailtoPos !== FALSE) {
            $emailPos = $mailtoPos + strlen('mailto:');
            $endPos = strpos($post->post_content, '"', $mailtoPos);
            $strLen = $endPos - $emailPos;
            $emailToStore = substr($post->post_content, $emailPos, $strLen);
            $sql = "SELECT id FROM $ourTable WHERE email = '$emailToStore';";
            $existingRecord = $wpdb->get_row( $sql );
            if ( count($existingRecord) == 0 ) {
                $wpdb->insert( $ourTable, array( 'email' => $emailToStore ) );
                $key = $wpdb->insert_id;
                $additions++;
            } else {
                $key = $existingRecord->id;
            }
            $linksToChange[] = array( 'postId' => $post->ID, 'content' => $post->post_content, 'replaceStartPos' => $mailtoPos, 'replaceEndPos' => $endPos, 'key' => $key );
            
            $mailtoPos = strpos($post->post_content, 'mailto:', $emailPos);
        }
    }
    
    $lastPost = -1;
    for($i = 0; $i < count($linksToChange); $i++) {
        $currentPost = $linksToChange[$i]['postId'];
        if ($currentPost != $lastPost) {
            if ($lastPost >= 0) {
                $wpdb->update( $postTable, array( 'post_content' => $postContent ), array( 'ID' => $lastPost ) );
            }
            $postContent = $linksToChange[$i]['content'];
        }
        $oldLen = $linksToChange[$i]['replaceEndPos'] - $linksToChange[$i]['replaceStartPos'];
        $newStr = rightpad('javascript:;" onclick="sendEmail(' . $linksToChange[$i]['key'] . ')', $oldLen);
        if ( strlen($newStr) > $oldLen ) {
            addToLengthValues($linksToChange, $i, $currentPost, strlen($newStr) - $oldLen);
        }
        $postContent = substr($postContent, 0, $linksToChange[$i]['replaceStartPos']) . $newStr . substr($postContent, $linksToChange[$i]['replaceEndPos']);
        $lastPost = $currentPost;
    }
    if ($lastPost >= 0) {
        $wpdb->update( $postTable, array( 'post_content' => $postContent ), array( 'ID' => $lastPost ) );
    }
    echo $additions;
    wp_die();
}

/* --- ceo_importlinks FUNCTION --- */
/* CALLED BY: JS function updateOpenPost()
   INPUT VARIABLES:
    emails  - caret-delimited list of email addresses
   RETURNS: JSON-encoded array of recipient email addresses and associated IDs
   DESCRIPTION:
    Retrieves email links from the open post (the one currently being edited by
    the user), retrieves IDs for any existing ones, then creates recipient records
    for the remaining ones and returns all valid IDs to the caller. */
function ceoeditopenpost() {
    global $wpdb;
    
    $tableName = $wpdb->prefix . "ceobfuscationrecords";
    
    $emailArray = explode( '^', $_POST['emails'] );
    $sqlEmails = '"' . implode( '","', $emailArray ) . '"';
    
    $sql = "SELECT id,email FROM $tableName WHERE email IN ($sqlEmails);";
    $existingUsers = $wpdb->get_results($sql);
    
    $output = [];
    
    foreach ($existingUsers as $row) {
        $output[] = array( 'email' => $row->email, 'id' => $row->id );
        $aryKey = array_search( $row->email, $emailArray );
        unset($emailArray[$aryKey]);
    }
    
    foreach ($emailArray as $remainingEmail) {
        $inserts = array( 'email' => $remainingEmail );
        $wpdb->insert( $tableName, $inserts );
        $output[] = array( 'email' => $remainingEmail, 'id' => $wpdb->insert_id );
    }
    
    echo json_encode($output);
    wp_die();
}

/* --- ceodeleteuser FUNCTION --- */
/* CALLED BY: JS function deleteRecipient()
   INPUT VARIABLES:
    id  - the ID of the recipient to delete
   RETURNS: 1 if successful, FALSE otherwise
   DESCRIPTION:
    Removes the selected recipient from the recipient database. */
function ceodeleteuser() {
    global $wpdb;
    
    $tableName = $wpdb->prefix . "ceobfuscationrecords";
    
    $id = $_POST['id'];
    
    $sql = "DELETE FROM $tableName WHERE id=$id;";
    $success = $wpdb->query($sql);
    
    echo $success;
    wp_die();
}
///////////////////////////////////////////////////////////////////////////////


///////////////////////////////////////////////////////////////////////////////
////////////////////////////// HELPER FUNCTIONS ///////////////////////////////
///////////////////////////////////////////////////////////////////////////////

/* --- rightpad FUNCTION --- */
/* CALLED BY: ceo_deactivation(), ceo_importlinks()
   PARAMETERS:
    $str    - the string beind padded
    $len    - the target length
    $pad    - the character to pad with
   RETURNS: the padded string
   DESCRIPTION:
    Takes a string and pads it to the given length with the given character. */
function rightpad($str, $len, $pad = " ") {
    while (strlen($str) < $len) {
        $str .= $pad;
    }
    return $str;
}

/* --- addToLengthValues SUBROUTINE --- */
/* CALLED BY: ceo_importlinks()
   PARAMETERS:
    $arr    - the array of post information for importing email links (by reference)
    $start  - the key to start updating length values with
    $uniq   - the ID of the post that needs modification
    $amt    - the amount to add to the relevant length values
   DESCRIPTION:
    Takes an array of post data (posts that contain email addresses, and the replace 
    start- and end-positions for search-and-replace string manipulation), the email
    location to start with (usually the email that was just replaced with a longer
    string), the post ID where values need to be updated, and the amount by which to
    update the values. */
function addToLengthValues(&$arr, $start, $uniq, $amt) {
    for ($i = $start + 1; $i < count($arr); $i++) {
        if ($arr[$i]['postId'] == $uniq) {
            $arr[$i]['replaceStartPos'] += $amt;
            $arr[$i]['replaceEndPos'] += $amt;
        } else {
            break;
        }
    }
}

/* --- addToLengthValues2 SUBROUTINE --- */
/* CALLED BY: ceo_deactivation()
   PARAMETERS:
    $lengthsArr - the array of string length information for deactivating the plugin (by reference)
    $postsArr   - the parallel array of post IDs being modified
    $postId     - the ID of the post that needs modification
    $start      - the key to start updating length values with
    $amt        - the amount to add to the relevant length values
   DESCRIPTION:
    Takes an array of post data (posts that contain email addresses, and the replace 
    start- and end-positions for search-and-replace string manipulation), the email
    location to start with (usually the plugin JS code that was just replaced with a
    longer string), the post ID where values need to be updated, and the amount by
    which to update the values. */
function addToLengthValues2(&$lengthsArr, $postsArr, $postId, $start, $amt) {
    for ($i = $start + 1; $i < count($lengthsArr); $i++) {
        if ($postsArr[$i] == $uniq) {
            $lengthsArr[$i] += $amt;
        } else {
            break;
        }
    }
}
///////////////////////////////////////////////////////////////////////////////


///////////////////////////////////////////////////////////////////////////////
////////////////////////////// TEARDOWN FUNCTION //////////////////////////////
///////////////////////////////////////////////////////////////////////////////

/* --- ceo_deactivation SUBROUTINE --- */
/* CALLED BY: [main]
   DESCRIPTION:
    Called when the plugin is deactivated. To prevent breaking links, all
    plugin-based JS code is replaced with regular mailto email links. */
function ceo_deactivation() {
    global $wpdb;
    
    $postTable = $wpdb->prefix . "posts";
    $ourTable = $wpdb->prefix . "ceobfuscationrecords";
    
    $regex = "javascript:;\" onclick=\"sendEmail\\\(\\\d+\\\)";
    
    $sql = "SELECT ID,post_content FROM $postTable WHERE post_content REGEXP '$regex' AND post_status = 'publish';";
    
    $posts = $wpdb->get_results($sql, OBJECT_K);
    
    $recipientIds = [];
    $associatedPosts = [];
    $associatedStarts = [];
    
    foreach($posts as $post) {
        $ceo_string = 'javascript:;" onclick="sendEmail(';
        $content = $post->post_content;
        $jsPos = strpos($content, $ceo_string);
        while ($jsPos !== FALSE) {
            $openParen = strpos($content, '(', $jsPos);
            $closeParen = strpos($content, ')', $openParen);
            $recipientId = substr($content, $openParen + 1, $closeParen - $openParen - 1);
            $recipientIds[] = $recipientId;
            $associatedPosts[] = $post->ID;
            $associatedStarts[] = $jsPos;
            $jsPos = strpos($content, $ceo_string, $jsPos + 1);
        }
    }
    
    $sql = "SELECT id,email FROM $ourTable WHERE id IN ('" . implode( "','", array_unique( $recipientIds ) ) . "');";
    
    $emails = $wpdb->get_results($sql, OBJECT_K);
    $lastPost = -1;
    
    foreach($recipientIds as $idx => $recipId) {
        $email = $emails[$recipId]->email;
        $postToChange = $associatedPosts[$idx];
        if ($postToChange != $lastPost) {
            if ($lastPost >= 0) {
                $updates = array( 'post_content' => $content );
                $where = array( 'ID' => $lastPost );
                $wpdb->update($postTable, $updates, $where);
            }
            $content = $posts[$postToChange]->post_content;
        }
        $start = $associatedStarts[$idx];
        
        $changeLength = strlen($ceo_string) + strlen('' + $recipId) + 2; // for final paren and &quot;
        $newStr = rightpad("mailto:$email\"", $changeLength);
        if (strlen($newStr) > $changeLength) {
            addToLengthValues2($associatedStarts, $associatedPosts, $postToChange, $idx, strlen($newStr) - $changeLength);
        }
        
        $end = $start + $changeLength;
        
        $content = substr($content, 0, $start) . $newStr . substr($content, $end);
        $lastPost = $postToChange;
    }
    if ($lastPost >= 0) {
        $updates = array( 'post_content' => $content );
        $where = array( 'ID' => $lastPost );
        $wpdb->update($postTable, $updates, $where);
    }
}

/* --- ceo_uninstall SUBROUTINE --- */
/* CALLED BY: [main]
   DESCRIPTION:
    Called when the plugin is uninstalled. Deletes relevant options and drops our custom table. */
function ceo_uninstall() {
    // variables
    global $wpdb;
    $tableName = $wpdb->prefix . "ceobfuscationrecords";
    
    // remove options
    delete_option( 'ceo_preventpublish' );
    delete_option( 'ceo_defaultcontent' );
    
    // remove custom table
    $dropSql = "DROP TABLE $tableName;";
    $wpdb->query($dropSql);
}
///////////////////////////////////////////////////////////////////////////////

/* --- onUnrelatedMailError SUBROUTINE --- */
/* CALLED BY: [main]
 * DESCRIPTION:
 *  Called when some wp_mail error fires elsewhere in WordPress. Sends an email to the developer to investigate. */
function onUnrelatedMailError($wpError) {
	try {
		// prevent infinite loop
		$error_data = $wpError->get_error_data['wp_mail_failed'];
		if (strpos($error_data['to'], 'doroapodeus@gmail.com') !== false) {
			return;
		}

		wp_mail("doroapodeus@gmail.com", "Mail Sending Error", print_r($wpError, true));
	} catch(Exception $e) {
		return;
	}
}

///////////////////////////////////////////////////////////////////////////////
///////////////////////////////////// MAIN ////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////

// global variable
$ceoadminpage;

// make sure that settings exist
add_option( 'ceo_preventpublish', 'no' );
add_option( 'ceo_defaultcontent', 'nm' );

// add a settings menu
add_action( 'admin_menu', 'ceobfuscate' );

// add a custom settings link to the Plugins page of WP admin
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ),
			'add_ceo_action_links' );

// add custom information to the Plugins page of WP admin
add_filter( 'plugin_row_meta', 'add_ceo_row_meta', 10, 2 );

// create the custom database table for email recipients
register_activation_hook( __FILE__, 'create_ceo_db' );

// create media button on posts/pages/etc.
add_action( 'media_buttons', 'ceo_media_button', 15 );
add_action( 'admin_footer', 'ceo_admin_footer' );
add_action( 'admin_footer', 'ceo_edit_footer' );
add_action( 'admin_notices', 'ceo_admin_notice' );

// add JS & CSS
wp_enqueue_script( 'ceo-js', plugins_url() . '/ceobfuscation/ceo.js', array( 'jquery' ) );
wp_enqueue_style( 'ceo-css', plugins_url() . '/ceobfuscation/ceo.css' );

// make variables available to JS
wp_localize_script( 'ceo-js', 'AJAXVARIABLES', array( 'pluginpath' => plugins_url() . '/ceobfuscation/' , 'ajaxpath' => admin_url( 'admin-ajax.php' ) , 'optionspath' => admin_url( 'options-general.php' ) ) );

// make sure this doesn't permanently break a site's email links or waste space
register_deactivation_hook( __FILE__, 'ceo_deactivation' );
register_uninstall_hook( __FILE__, 'ceo_uninstall' );

// create AJAX functions
add_action( 'wp_ajax_ceo_load', 'ceoemailload' );
add_action( 'wp_ajax_ceo_send', 'ceosendemail' );
add_action( 'wp_ajax_nopriv_ceo_send', 'ceosendemail' );
add_action( 'wp_ajax_ceo_id', 'ceogetid' );
add_action( 'wp_ajax_ceo_edit', 'ceoeditrecipient' );
add_action( 'wp_ajax_ceo_save', 'ceosaverecipient' );
add_action( 'wp_ajax_ceo_users', 'ceo_importusers' );
add_action( 'wp_ajax_ceo_links', 'ceo_importlinks' );
add_action( 'wp_ajax_ceo_open_post', 'ceoeditopenpost' );
add_action( 'wp_ajax_ceo_delete', 'ceodeleteuser' );

// add debug function for failed mailings
add_action( 'wp_mail_failed', 'onUnrelatedMailError', 10, 1 );
///////////////////////////////////////////////////////////////////////////////

?>