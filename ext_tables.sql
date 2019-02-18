# This extra field for the 'pages' table is used to create a 
# checkbox in the page properties, directly next to the slug 
# input field. If this is activated, the slug can no longer 
# be overwritten by the slug module.

CREATE TABLE pages (
    slug_locked smallint(5) unsigned DEFAULT '0' NOT NULL
);
