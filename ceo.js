/////////////////////
// --- GLOBALS --- //
/////////////////////
var emailRecipients = []; // array of email recipients
var recipientIds = []; // array of recipient IDs
var currentFocus; // tracks the user's focus in our search-as-you-type system
var savedOnce = false; // tracks whether the user has tried to save more than once (we only warn them once about un-obfuscated links)
/////////////////////
//// END GLOBALS ////
/////////////////////

////////////////////////////
// --- AJAX FUNCTIONS --- //
////////////////////////////
// loadRecipients()
// Loads all existing recipient records and saves them to the emailRecipients and recipientIds globals.
function loadRecipients() {
    jQuery(document).ready(function($) {
        var data = {
            'action': 'ceo_load'
        };
        jQuery.post(AJAXVARIABLES.ajaxpath, data, function(response) {
            var jObject = JSON.parse(response);
            emailRecipients = [];
            recipientIds = [];
            recipientPrefixes = [];
            recipientSuffixes = [];
            recipientNames = [];
            for (var recip of jObject) {
                emailRecipients.push(recip.email);
                recipientIds.push(recip.id);
                recipientPrefixes.push(recip.prefix);
                recipientSuffixes.push(recip.suffix);
                recipientNames.push(recip.name);
            };
        });
    });
}

// returnEmailRecipientId(str, addToEditor, contentToUse, linkClass)
//  str         - the email address to find a recipient ID for
//  addToEditor - whether we should add the link to the post editor directly (TRUE) or return it to the caller (FALSE)
//  contentToUse- only used if addToEditor is TRUE; the display text of our email link
//  linkClass   - only used if addToEditor is TRUE; the class to use for the email link
// Called when an email address is selected to add to a post. May add asynchronously (if addToEditor is TRUE).
function returnEmailRecipientId(str, addToEditor, contentToUse, linkClass) {
    if (str.length == 0) {
        return;
    } else {
        jQuery(document).ready(function($) {
            var data = {
                'action': 'ceo_id',
                's': str
            };
            jQuery.post(AJAXVARIABLES.ajaxpath, data, function(response) {
                if (addToEditor) {
                    putLinkInEditor(response, contentToUse, linkClass);
                    jQuery("#ceoaddtopost .ceospinner").css('visibility','hidden');
                    tb_remove();
                } else {
                    return response;
                }
            });
        });
    }
}

// ceo_import_users()
// Imports WordPress users to the recipient database via an asynchronous PHP function.
function ceo_import_users() {
    jQuery(document).ready(function($) {
        var data = {
            'action': 'ceo_users'
        };
        jQuery.post(AJAXVARIABLES.ajaxpath, data, function(response) {
            window.setTimeout(function() { // try to wait for the AJAX to finish
                window.location.href = AJAXVARIABLES.optionspath + '?page=emailobfuscation-settings&success=' + response;
            });
        });
    });
}

// ceo_import_emails()
// Imports and converts all existing mailto links in the WP posts database.
function ceo_import_emails() {
    jQuery(document).ready(function($) {
        var data = {
            'action': 'ceo_links'
        };
        jQuery.post(AJAXVARIABLES.ajaxpath, data, function(response) {
            window.setTimeout(function() { // try to wait for the AJAX to finish
                window.location.href = AJAXVARIABLES.optionspath + '?page=emailobfuscation-settings&success=' + response;
            })
        });
    });
}

// loadRecipientEditor(id)
//  id  - the recipient ID we want to edit
// Loads data for a single recipient into a ThickBox editor (admin side)
function loadRecipientEditor(id) {
    jQuery("#ceoeditrecipient .ceospinner").css('visibility','visible');
    document.getElementById('ceoid').value = '';
    document.getElementById('ceoname').value = '';
    document.getElementById('ceoemail').value = '';
    document.getElementById('ceosuffix').value = '';
    for (var opt of document.getElementById('ceoprefix').childNodes) {
        opt.selected = false;
    }
    jQuery(document).ready(function($) {
        var data = {
            'action': 'ceo_edit',
            'u': id
        };
        jQuery.post(AJAXVARIABLES.ajaxpath, data, function(response) {
            var recipData = JSON.parse(response);
            document.getElementById('ceoid').value = recipData.id;
            document.getElementById('ceoname').value = recipData.name;
            document.getElementById('ceoemail').value = recipData.email;
            document.getElementById('ceosuffix').value = recipData.suffix;
            var prefixSelect = document.getElementById('ceoprefix');
            for (var opt of prefixSelect.childNodes) {
                if (opt.value == recipData.prefix) {
                    opt.selected = true;
                    break;
                }
            }
            jQuery("#ceoeditrecipient .ceospinner").css('visibility','hidden');
        });
    });
}

// saveRecipient()
// Closes the current ThickBox editor and saves the data from its fields for this recipient (admin side)
function saveRecipient() {
    if (typeof UPDATESUCCESS != 'undefined') UPDATESUCCESS = undefined;
    
    var id = document.getElementById('ceoid').value;
    var name = document.getElementById('ceoname').value;
    var email = document.getElementById('ceoemail').value;
    var prefix = document.getElementById('ceoprefix').value;
    var suffix = document.getElementById('ceosuffix').value;
    
    jQuery(document).ready(function($) {
        var data = {
            'action': 'ceo_save',
            'id': id,
            'name': name,
            'email': email,
            'prefix': prefix,
            'suffix': suffix
        };
        jQuery.post(AJAXVARIABLES.ajaxpath, data, function(response) {
            window.setTimeout(function() { // try to wait for the AJAX to finish
                window.location.href = AJAXVARIABLES.optionspath + '?page=emailobfuscation-settings&success=' + UPDATESUCCESS;
            });
        });
    });
}

// updateOpenPost()
// Retrieves mailto links from the currently edited post and converts them.
function updateOpenPost() {
    var emails = [];
    
    var content = jQuery("#content").html();
    var start = 0;
    var mailto = "\"mailto:"
    
    while (content.includes(mailto, start)) {
        var emailStart = content.indexOf(mailto) + mailto.length;
        var email = content.slice(emailStart, content.indexOf("\"", emailStart));
        emails.push(email);
        start = emailStart;
    }
    
    emails = emails.join('^');
    
    jQuery(document).ready(function($) {
        var data = {
            'action': 'ceo_open_post',
            'emails': emails
        };
        jQuery.post(AJAXVARIABLES.ajaxpath, data, function(response) {
            var jObject = JSON.parse(response);
            for (var recip of jObject) {
                content = content.replace(mailto + recip.email, "\"javascript:;\" onclick=\"sendEmail(" + recip.id + ")");
            }
            document.getElementById("content").innerHTML = content;
            document.getElementById("ceo_notice").classList.add("hidden");
        });
    });
}

// deleteRecipient()
// Closes the ThickBox and deletes the edited recipient (admin side)
function deleteRecipient() {
    var id = document.getElementById('ceoid').value;
    
    jQuery(document).ready(function($) {
        var data = {
            'action': 'ceo_delete',
            'id': id
        };
        jQuery.post(AJAXVARIABLES.ajaxpath, data, function(response) {
            window.setTimeout(function() {
                window.location.href = AJAXVARIABLES.optionspath + '?page=emailobfuscation-settings&deleted=' + response;
            });
        })
    });
}
////////////////////////////
//// END AJAX FUNCTIONS ////
////////////////////////////

/////////////////////////////
// --- BASIC FUNCTIONS --- //
/////////////////////////////
// sendEmail(userID)
//  userID  - the ID of the recipient to whom we want to send an email
// Called by the basic JS function of the plugin; meant to replace/obfuscate mailto links. Redirects the user to a mailto link that bots can't find.
function sendEmail(userID) {
    jQuery(document).ready(function($) {
        var data = {
            'action': 'ceo_send',
            'u': userID
        };
        jQuery.post(AJAXVARIABLES.ajaxpath, data, function(response) {
            if (response.length > 0) {
                window.location.href = "mailto:" + response;
            } else {
                alert("This link has been set up incorrectly. Please contact the site administrator.");
            }
        });
    });
}

// addLinkToText()
// Using the ThickBox search-and-insert fields, adds a link to the current post being edited.
function addLinkToText() {
    var email = document.getElementById("ceoSearch").value;
    var content = document.getElementById("ceoLinkContent").value;
    var linkClass = document.getElementById("ceoLinkClass").value;
    
    if(email.length == 0) {
        alert('You must enter an email address.');
        return;
    }
    var id = document.getElementById("ceoIdResult").value;
    if(id.length > 0) {
        putLinkInEditor(id, content, linkClass);
        tb_remove();
    } else {
        jQuery("#ceoaddtopost .ceospinner").css('visibility','visible');
        returnEmailRecipientId(email, 1, content, linkClass);
    }
}
/////////////////////////////
//// END BASIC FUNCTIONS ////
/////////////////////////////

//////////////////////////////
// --- SEARCH FUNCTIONS --- //
//////////////////////////////
// findEmail(str)
//  str - the email address to find
// Called in our search-as-you-type functionality. Clears the "drop-down" list and adds options for each recipient whose email starts with the given string.
function findEmail(str) {
    var list = document.getElementById("ceoSearchItems");
    
    removeChildren(list);
    if (str.length > 0) {
        for (var recip in emailRecipients) {
            if (emailRecipients.hasOwnProperty(recip)) {
                if (emailRecipients[recip].startsWith(str)) {
                    addOption(emailRecipients[recip], recipientIds[recip], recipientPrefixes[recip], recipientSuffixes[recip], recipientNames[recip]);
                }
            }
        }
    }
}

// addOption(item, id, prefix, suffix, name)
//  item    - the recipient's email address
//  id      - the recipient's ID
//  prefix  - the recipient's prefix (Mr., Mrs., etc.)
//  suffix  - the recipient's suffix (Jr., Sr., etc.)
//  name    - the recipient's full name (First Last)
// Called in our search-as-you-type functionality. Adds an option to the "drop-down" for a recipient that matches the current search string.
function addOption(item, id, prefix, suffix, name) {
    var list = document.getElementById("ceoSearchItems");
    var op = document.createElement("div");
    var span = document.createElement("span");
    span.innerHTML = item;
    var idInp = document.createElement("input");
    idInp.type = "hidden";
    idInp.value = id;
    idInp.classList.add("id");
    var nmInp = document.createElement("input");
    nmInp.type = "hidden";
    nmInp.value = name;
    nmInp.classList.add("nm");
    var fullInp = document.createElement("input");
    fullInp.type = "hidden";
    fullInp.value = prefix + " " + name + " " + suffix;
    fullInp.classList.add("full");
    op.appendChild(span);
    op.appendChild(idInp);
    op.appendChild(nmInp);
    op.appendChild(fullInp);
    op.onclick = function() {
        var searchbox = document.getElementById("ceoSearch");
        var list = document.getElementById("ceoSearchItems");
        var id = document.getElementById("ceoIdResult");
        searchbox.value = this.getElementsByTagName("span")[0].innerHTML;
        id.value = this.getElementsByClassName("id")[0].value;
        var contentBox = document.getElementById("ceoLinkContent");
        if (ceoContent == 'nm') {
            contentBox.value = this.getElementsByClassName("nm")[0].value;
            if (contentBox.value == "") contentBox.value = searchbox.value;
        } else if (ceoContent == 'full') {
            contentBox.value = this.getElementsByClassName("full")[0].value;
            if (contentBox.value == "") contentBox.value = searchbox.value;
        } else if (ceoContent == 'eml') {
            contentBox.value = searchbox.value;
        } else {
            contentBox.value = "";
        }
        removeChildren(list);
        currentFocus = -1;
    };
    list.appendChild(op);
}

// ceo_autocomplete()
// Primary control function for the search-as-you-type functionality. Tracks focus, controls movement, selection, adding/removing children, etc.
function ceo_autocomplete() {
    var searchbox = document.getElementById("ceoSearch");
    var list = document.getElementById("ceoSearchItems");
    currentFocus = -1;
    
    searchbox.addEventListener("input", function(e) {
        findEmail(this.value);
    });

    searchbox.addEventListener("keydown", function(e) {
        var results = list.getElementsByTagName("div");
        if (e.keyCode == 40) { // down
            currentFocus++;
            showActive(results);
        } else if (e.keyCode == 38) { // up
            currentFocus--;
            showActive(results);
        } else if (e.keyCode == 13 || e.keyCode == 9) { // enter or tab
            if (currentFocus > -1) {
                if (e.keyCode == 13) e.preventDefault(); // don't submit the form, but let tab do its job
                results[currentFocus].click();
            } else {
                var done = false;
                for (var i = 0; i < results.length; i++) {
                    if (results[i].getElementsByTagName("span")[0].innerHTML == this.value) {
                        results[i].click();
                        done = true;
                        break;
                    }
                }
                if (!done) {
                    removeChildren(list);
                }
            }
        }
    });
    
    function showActive(results) {
        deactivate(results); // make nothing active
        if (results.length <= 0) return; // there is nothing to make active
        if (currentFocus >= results.length) currentFocus = 0; // wrap bottom to top
        else if (currentFocus < 0) {
            currentFocus = -1;
            searchbox.focus();
            return; // don't wrap top to bottom
        }
        if (currentFocus >= 0)
            results[currentFocus].classList.add("ceo-autocomp-active"); // make active
    }
    
    function deactivate(results) {
        for (var i = 0; i < results.length; i++) {
            results[i].classList.remove("ceo-autocomp-active");
        }
    }
    
    document.addEventListener("click", removeChildren(list));
}
//////////////////////////////
//// END SEARCH FUNCTIONS ////
//////////////////////////////

//////////////////////////////
// --- HELPER FUNCTIONS --- //
//////////////////////////////
// removeChildren(el)
//  el  - an element that has children
// Removes all child elements from a given parent element.
function removeChildren(el) {
    while (el.lastChild) {
        el.removeChild(el.lastChild);
    }
}

// ceoShowHelp(el)
//  el  - a help notification element/button
// Shows the help text associated with a setting based on which notification button was clicked.
function ceoShowHelp(el) {
    var helpDiv = el.parentElement.parentElement.getElementsByClassName("ceo-hidden-help")[0];
    if (helpDiv.classList.contains("hidden")) {
        helpDiv.classList.remove("hidden");
    } else {
        helpDiv.classList.add("hidden");
    }
}

// putLinkInEditor(id, content, linkClass)
//  id          - the recipient ID to add
//  content     - the text of the link
//  linkClass   - the class of the link (optional)
// Asynchronously adds a link to the editor on the current page (protected by a loading overlay)
function putLinkInEditor(id, content, linkClass) {
    var output = '<a href="javascript:;" class="' + linkClass + '" onclick="sendEmail(' + id + ')">' + content + '</a>';
    
    window.send_to_editor(output);
}
//////////////////////////////
//// END HELPER FUNCTIONS ////
//////////////////////////////

/////////////////////////////////
// --- WORDPRESS FUNCTIONS --- //
/////////////////////////////////
// resize_tb(width, height)
//  width   - target width for the ThickBox
//  height  - target height for the ThickBox
// Since tb_position gets overridden by WP's media_upload.js, this function fixes it for our purposes.
function resize_tb(width,height) {
    var tbWindow = document.getElementById("TB_window");
    var tbTitle = document.getElementById("TB_ajaxWindowTitle");
    var linkButton = document.getElementById("ceo_add_button");
    if (tbTitle.innerHTML == "Add Email Link" && tbWindow.style.length > 0) {
        tbWindow.style.width = width + "px";
        tbWindow.style.height = height + "px";
        tbWindow.style.marginLeft = "-" + parseInt( ( ( width - 50 ) / 2 ), 10 ) + 'px';
        tbWindow.style.top = '200px';
        var href = linkButton.href;
        href = href.replace(/&width=[0-9]+/g, '');
        href = href.replace(/&height=[0-9]+/g, '');
        linkButton.href = linkButton.href + '&width=350&height=250';
        document.getElementById("ceoSearch").value = '';
        document.getElementById("ceoLinkContent").value = '';
        document.getElementById("ceoIdResult").value = '';
    }
}

// watchForMutationThenAct()
// Use a MutationObserver to wait until the ThickBox has been created and sized, then resize it to fit our settings.
function watchForMutationThenAct() {
    var targetNode = document.body;
    var config = { childList: true };
    var observer = new MutationObserver(function(mutations) {
        for(var mutation of mutations) {
            if (mutation.type == 'childList') {
                var titleDiv = document.getElementById("TB_ajaxWindowTitle");
                if (titleDiv) {
                    resize_tb(400, 300);
                    observer.disconnect();
                }
            }
        }
    });
    observer.observe(targetNode, config);
}
/////////////////////////////////
//// END WORDPRESS FUNCTIONS ////
/////////////////////////////////

//////////////////
// --- MAIN --- //
//////////////////
// watches for post submissions and prevents them (if the notice does not exist, then settings indicate we should not prevent posting)
jQuery(document).ready(function() {
    jQuery("#post").submit(function() {
        if (savedOnce) {
            savedOnce = false;
            return true;
        }
        $post_content = jQuery("#content").html();
        if ($post_content.includes("\"mailto:")) {
            var notice = document.getElementById("ceo_notice");
            if (notice != null) {
                notice.classList.remove("hidden");
                jQuery('.spinner').css("visibility","hidden");
                savedOnce = true;
                return false;
            } else {
                return true;
            }
        }
    });
});
//////////////////
//// END MAIN ////
//////////////////