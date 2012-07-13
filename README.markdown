# Content Negotiation for Symphony CMS

This extension creates additional page templates to serve different content types from a single Symphony page. It finds the appropriate template either via the file extension in the `URL` or via the `Accept` header from the http request and adds a corresponding `Content-Type` header in the response.

## Installation

1. Just install it like any other Symphony CMS extension.
2. You might want to manually [modify the `.htaccess` file](#htaccess-modifications) to get clean `URLs` for the file extension.

## Usage

During installation the extension adds a setting with some standard content types to the `config.php`.

	'content_negotiation' => array(
		'html' => 'text/html',
		'xml' => 'application/xml',
		'json' => 'application/json',
		'jsonp' => 'text/javascript',
		'csv' => 'application/csv',
		'php' => 'text/plain',
	),

Modify it to your needs. This array sets the supported content types that pages can use.

Now, when you create or edit a Symphony page the extension compares the page types with the keys of the array of the supported content types and creates a new page tempate for each match. For example when you create a page with the types `json` and `xml` you'll find the normal `page-handle.xsl` in the `/workspace/pages/` folder, and additionally `page-handle.json.xsl` and `page-handle.xml.xsl`.

The same comparison is done on frontend requests as well. At first the value of the `$_REQUEST['content-type']` (nicely mapped via the `.htaccess` rule) is used to find a match in the keys of the negotiable content types (the union of the supported types and the page types). If that's not successful, the `Accept` header of the http request gets evaluated to find the most appropriate match. The value of the found content type is then used as the value of the `Content-Type` header in the response.

### `.htaccess` Modifications

When you want to be able to set the content type via the file extension in a clean `URL` you have to prevent Symphony from adding a trailing slash to the end of such an `URL`. Just add the following two lines before the `RewriteRule` in the `CHECK FOR TRAILING SLASH` block.

	### DO NOT ADD TRAILING SLASH WHEN URI SETS A CONTENT TYPE
	RewriteCond %{REQUEST_URI} !(.*)\.(.+)$

The extension expects the format to get passed as the `content-type` parameter. Adding the following block just before the `FRONTEND REWRITE` block makes that happen.

	### FRONTEND REWRITE with content type extension - Will ignore files and folders
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule ^(.*)\.(.+)$ index.php?symphony-page=$1&content-type=$2&%{QUERY_STRING}	[L]

## Important

This extension is in an experimental state. Use at your own risk.

There are some things not working and the inner and outer workings might change without being backwards compatible.

### What doesn't work yet

- Deletion of format specific page templates, both when pages get deleted or page types removed.
- I haven't tested the extension in combination with the [Content Type Mappings](https://github.com/symphonycms/content_type_mappings) extension. I don't expect good things.

### And some future ideas

- Support for additional headers, e.g. `Content-Disposition` to trigger downloads.
- Delegates to modify the output via PHP, e.g. for PDF generation. But the extension might also work with existing extensions.
