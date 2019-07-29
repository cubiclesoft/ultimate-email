EmailBuilder Class:  'support/email_builder.php'
================================================

EmailBuilder is a powerful generator/builder class that creates beautiful, fully responsive, table-based HTML emails to be used later with the SMTP class.  Uses a natural PHP arrays approach.

Example:

```php
	require_once "support/email_builder.php";

	// Write normal CSS.
	$styles = array(
		"a" => "text-decoration: none;",
		"#headerwrap" => "font-size: 0; line-height: 0;",
		"#contentwrap" => "font-family: Helvetica, Arial, sans-serif; font-size: 18px; line-height: 27px; color: #333333;",
		"#contentwrap a:not(.bigbutton)" => "color: #4E88C2;",
		"#contentwrap a.bigbutton" => "font-family: Helvetica, Arial, sans-serif; font-size: 18px; line-height: 27px; color: #FEFEFE;",
		"#footerwrap" => "font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 21px; color: #F0F0F0;",
		"#footerwrap a" => "color: #CCCCCC;"
	);

	$content = array(
		// Header.
		array(
			"type" => "layout",
			"id" => "headerwrap",
//			"table-bgcolor" => "#FF0000",
			"width" => 600,
			"content" => array(
				array(
					"type" => "image",
					"width" => 600,
//					"src" => "http://localhost/ultimate-email/test_suite/test_newsletter_header.png",
					"file" => "test_suite/test_newsletter_header.png"
				)
			)
		),

		// Main content.
		array(
			"type" => "layout",
			"width" => 600,
			"content" => array(
				array(
					"type" => "layout",
					"id" => "contentwrap",
					"width" => "90%",
					"content" => array(
						array("type" => "space", "height" => 1),

						"<p>Hello valued humanoid!</p>",

						// Float an image to the right.
						array(
							"type" => "layout",
//							"table-width" => "35%",
							"table-align" => "right",
							"width" => 100,
							"padding" => array(5, 0, 20, 20),
							"content" => array(
								array(
									"type" => "image",
									"width" => 100,
									"src" => "http://lorempixel.com/g/100/150/technics/1/",
									"alt" => "Circuitry"
								)
							)
						),

						"<p>Blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah</p>",
						"<p>Blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah</p>",
						"<p><a href=\"#\">Blah blah blah</a></p>",
						"<p>Blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah blah</p>",

						// Display a clickable link as a centered button (e.g. a call to action).
						array(
							"type" => "layout",
							"align" => "center",
							"content" => array(
								array(
									"type" => "button",
									"href" => "#",
//									"target" => "_blank",
									"class" => "bigbutton",
									"bgcolor" => "#4E88C2",
									"padding" => array(12, 18),
									"text" => "Take the survey"
								)
							)
						),

						// Spacers and a splitter.
						array("type" => "space", "height" => 20),
						array("type" => "split", "bgcolor" => "#F0F0F0"),
						array("type" => "space", "height" => 5),

						"<p>Sincerely,</p>",
						"<p>Blah blah blah</p>",

						array("type" => "space", "height" => 1),
					)
				)
			)
		),

		// Footer.
		array(
			"type" => "layout",
			"width" => 600,
			"bgcolor" => "#182434",
			"content" => array(
				array(
					"type" => "layout",
					"id" => "footerwrap",
					"width" => "90%",
					"content" => array(
						array("type" => "space", "height" => 1),

						"<p>Blah blah blah</p>",
						"<p><a href=\"#\">Unsubscribe</a></p>",
						"<p>Blah blah blah</p>",

						array("type" => "space", "height" => 1),
					)
				)
			)
		)
	);

	$result = EmailBuilder::Generate($styles, $content);

echo $result["html"];
?>
```

Output:

![EmailBuilder example](https://user-images.githubusercontent.com/1432111/62052744-f9903b00-b1ca-11e9-992c-d911af497be3.png)

EmailBuilder::RegisterHandler($mode, $callback)
-----------------------------------------------

Access:  public static

Parameters:

* $mode - A string containing one of "init", "custom_type", or "finalize".
* $callback - A valid callback function for the specified mode.  The callback function must accept the correct number and type of inputs.

Returns:  Nothing.

This static function is the basis of adding new functionality to the EmailBuilder class.  Callbacks are executed in a specific sequence for managing field modifications in an efficient manner.

EmailBuilder::InitContentOpts(&$contentopts)
--------------------------------------------

Access:  protected static

Parameters:

* $contentopts - An array containing options used to generate the HTML.

Returns:  Nothing.

This internal static function initializes any registered "init" handlers.  The $contentopts array may be modified.

EmailBuilder::AlterOption(&$html, &$inlineimages, &$option, $prefix)
--------------------------------------------------------------------

Access:  protected static

Parameters:

* $html - A string containing the HTML generated so far.
* $inlineimages - An array containing inline images added so far.
* $option - An array containing the current option being processed.
* $prefix - A string containing the current output prefix.

Returns:  Nothing.

This internal static function allows any registered "custom_type" handlers to process the current option.

EmailBuilder::Finalize(&$html, &$contentopts)
---------------------------------------------

Access:  protected static

Parameters:

* $html - A string containing the HTML generated so far.
* $contentopts - An array containing options used to generate the HTML.

Returns:  Nothing.

This internal static function runs any registered "finalize" handlers.

EmailBuilder::ProcessContentOpts(&$html, &$inlineimages, &$contentopts, $prefix)
--------------------------------------------------------------------------------

Access:  _internal_ static

Parameters:

* $html - A string containing the HTML generated so far.
* $inlineimages - An array containing inline images added so far.
* $contentopts - An array containing the current level of options being processed.
* $prefix - A string containing the current output prefix.

Returns:  Nothing.

This mostly internal static function processes the current level of options to generate the correct HTML output.  Options may be processed recursively.

EmailBuilder::Generate($styles, $contentopts)
---------------------------------------------

Access:  public static

Parameters:

* $styles - An array of key-value pairs that maps CSS3 selectors to strings containing styles to apply to the targets.
* $contentopts - An array of options that describe how to generate the HTML content.

Returns:  A standard array of information.

This static function takes in two arrays of information containing CSS3 styles to apply inline and a set of options that defines how the HTML will be generated for the email.  First, `EmailBuilder::ProcessContentOpts()` is called and the HTML is generated.  Then inline styles are applied to the generated HTML using the $styles array, 'id' and 'class' attributes are removed, and the result is returned.

The $contentopts array is very flexible and options vary depending on the option.  An option can be a string or an array.  When the option is a string, it is assumed to be valid HTML and included as-is (NOTE:  TagFilter may correct invalid HTML later in the process).

When the option is an array of key-value pairs, the "type" may be one of:

* layout - A highly flexible horizontal layout with positioning, including float emulation support.
* space - A vertical spacer.
* split - A horizontal line/splitter.
* button - A clickable link displayed as a button (e.g. call to action).
* image - A responsive/scaled image with optional inline embedding support (i.e. `cid:`).

Type-specific options:

* padding (layout, button) - An integer or array of integers containing standard CSS padding declarations for top, right, bottom, left.
* table-width (layout) - A string or integer containing the width of the table (Default is "100%").  Should be a percentage or in pixels.
* table-bgcolor (layout) - A string containing the background color for the table.  Should be in the format "#RRGGBB" for maximum compatibility.
* table-class (layout) - A string containing one or more CSS classes to apply to the table element.
* table-align (layout) - A string containing one of "left", "right", "center" to apply to the table element.  Useful for simulating floats.
* width (layout) - A string or integer containing the width of the main content area for the layout.  When both 'width' and 'padding' are defined, 'table-width' is forced to be a precise pixel count.
* align (layout) - A string containing one of "left", "right", "center" to align the content inside.
* row-align (layout) - A string containing one of "left", "right", "center" to align the content region when 'width' is defined and 'padding' is not defined (Default is "center").  This creates a responsive content layout.
* bgcolor (layout, space, split, button) - A string containing the background color to use.  For layouts, this is the background color of the content area.  Should be in the format "#RRGGBB" for maximum compatibility.
* style (layout, button, image) - A string containing CSS styles to append to the style attribute.  For layouts, this is applied to the content area.
* id (layout, button, image) - A string containing a CSS ID to apply.  For layouts, this is applied to the content area.
* class (layout, button, image) - A string containing one or more CSS classes to apply.  For layouts, this is applied to the content area.
* content (layout) - An array containing more $contentopts options.  `EmailBuilder::ProcessContentOpts()` is called recursively to process the options.
* height (space, split) - An integer containing the number of vertical pixels for the spacer/splitter.
* border-radius (button) - An integer containing the number of pixels to use for the corners of the button (Default is 4).
* href (button) - A string containing the URL to link to.
* target (button) - A string containing the window target (e.g. "_blank").
* text (button) - A string containing the text to use.  Will be escaped.
* html (button) - A string containing the HTML to use.
* file (image) - A string containing the filename of the file to embed/inline as a "cid:" (Content-ID) image.  The file must be JPEG, PNG, or GIF.  May appear as a file attachment in some email clients in addition to being embedded.
* src (image) - A string containing the URL of the image to load.  Note that email clients won't automatically load this type of image.
* alt (image) - A string containing alt text for the image.
* width (image) - An integer containing the width, in pixels, to display the image.
* height (image) - An integer containing the height, in pixels, to display the image.
