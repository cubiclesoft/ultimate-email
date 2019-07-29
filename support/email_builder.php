<?php
	// Email builder/designer class.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	class EmailBuilder
	{
		protected static $handlers;

		public static function RegisterHandler($mode, $callback)
		{
			if (!isset(self::$handlers) || !is_array(self::$handlers))  self::$handlers = array("init" => array(), "custom_type" => array(), "finalize" => array());

			if (isset(self::$handlers[$mode]))  self::$handlers[$mode][] = $callback;
		}

		protected static function InitContentOpts(&$contentopts)
		{
			// Let form handlers modify the array.
			foreach (self::$handlers["init"] as $callback)
			{
				if (is_callable($callback))  call_user_func_array($callback, array(&$contentopts));
			}
		}

		protected static function AlterOption(&$html, &$inlineimages, &$option, $prefix)
		{
			// Let form handlers process custom, modified, and other field types.
			foreach (self::$handlers["custom_type"] as $callback)
			{
				if (is_callable($callback))  call_user_func_array($callback, array(&$html, &$inlineimages, &$option, $prefix));
			}
		}

		protected static function Finalize(&$html, &$contentopts)
		{
			// Let form handlers process custom, modified, and other field types.
			foreach (self::$handlers["finalize"] as $callback)
			{
				if (is_callable($callback))  call_user_func_array($callback, array(&$html, &$contentopts));
			}
		}

		public static function ProcessContentOpts(&$html, &$inlineimages, &$contentopts, $prefix)
		{
			self::InitContentOpts($contentopts);

			foreach ($contentopts as $option)
			{
				if (is_string($option))  $html .= $prefix . $option . "\n";
				else if (!is_array($option) || !isset($option["type"]))  continue;
				else
				{
					self::AlterOption($html, $inlineimages, $option, $prefix);

					switch ($option["type"])
					{
						case "layout":
						{
							if (isset($option["padding"]) && !is_array($option["padding"]))  $option["padding"] = array($option["padding"]);

							if (isset($option["padding"]))
							{
								if (count($option["padding"]) == 1)  $option["padding"] = array($option["padding"][0], $option["padding"][0], $option["padding"][0], $option["padding"][0]);
								else if (count($option["padding"]) == 2)  $option["padding"] = array($option["padding"][0], $option["padding"][1], $option["padding"][0], $option["padding"][1]);
								else if (count($option["padding"]) == 3)  $option["padding"] = array($option["padding"][0], $option["padding"][1], $option["padding"][2], $option["padding"][1]);

								$numcols = ($option["padding"][3] > 0 ? 1 : 0) + 1 + ($option["padding"][1] > 0 ? 1 : 0);
							}

							// Set 'table-width' if both 'width' and 'padding' are specified.
							if (isset($option["width"]) && isset($option["padding"]) && is_array($option["padding"]))
							{
								$option["width"] = (int)$option["width"];
								$option["table-width"] = $option["padding"][1] + $option["width"] + $option["padding"][3];
							}

							if (!isset($option["table-width"]))  $option["table-width"] = "100%";
							$option["table-width"] = (string)$option["table-width"];
							if (strpos($option["table-width"], "%") === false && strpos($option["table-width"], "px") === false)  $option["table-width"] = (int)$option["table-width"] . "px";

							$html .= $prefix . "<table style=\"padding: 0; margin: 0; width: " . htmlspecialchars($option["table-width"]) . ";" . (isset($option["table-bgcolor"]) ? " background-color: " . htmlspecialchars($option["table-bgcolor"]) . ";" : "") . "\"" . (isset($option["table-class"]) ? " class=\"" . htmlspecialchars($option["table-class"]) . "\"" : "") . " width=\"" . htmlspecialchars((strpos($option["table-width"], "%") === false ? (int)$option["table-width"] : $option["table-width"])) . "\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\"" . (isset($option["table-bgcolor"]) ? " bgcolor=\"" . htmlspecialchars($option["table-bgcolor"]) . "\"" : "") . (isset($option["table-align"]) ? " align=\"" . htmlspecialchars($option["table-align"]) . "\"" : "") . ">\n";

							if (isset($option["padding"]) && $option["padding"][0] > 0)
							{
								$html .= $prefix . "\t<tr>";
								$html .= "<td colspan=\"" . $numcols . "\" style=\"padding: 0; margin: 0; font-size: 0; line-height: 0; height: " . (int)$option["padding"][0] . "px;\" height=\"" . (int)$option["padding"][0] . "\"></td>";
								$html .= "</tr>\n";
							}

							$html .= $prefix . "\t<tr>\n";
							if (isset($option["padding"]))
							{
								if ($option["padding"][3] > 0)  $html .= $prefix . "\t\t<td style=\"padding: 0; margin: 0; font-size: 0; line-height: 0; width: " . (int)$option["padding"][3] . "px; height: 1px;\" width=\"" . (int)$option["padding"][3] . "\" height=\"1\"></td>\n";
							}
							else if (isset($option["width"]) && (!isset($option["row-align"]) || $option["row-align"] !== "left"))
							{
								$html .= $prefix . "\t\t<td style=\"padding: 0; margin: 0; font-size: 0; line-height: 0; height: 1px;\" height=\"1\"></td>\n";
							}

							$html .= $prefix . "\t\t<td style=\"padding: 0; margin: 0;" . (isset($option["bgcolor"]) ? " background-color: " . htmlspecialchars($option["bgcolor"]) . ";" : "") . (isset($option["style"]) ? " " . htmlspecialchars($option["style"]) : "") . "\"" . (isset($option["id"]) ? " id=\"" . htmlspecialchars($option["id"]) . "\"" : "") . (isset($option["class"]) ? " class=\"" . htmlspecialchars($option["class"]) . "\"" : "") . (isset($option["width"]) ? " width=\"" . htmlspecialchars($option["width"]) . "\"" : "") . (isset($option["bgcolor"]) ? " bgcolor=\"" . htmlspecialchars($option["bgcolor"]) . "\"" : "") . (isset($option["align"]) ? " align=\"" . htmlspecialchars($option["align"]) . "\"" : "") . ">\n";

							if (isset($option["content"]) && is_array($option["content"]))  self::ProcessContentOpts($html, $inlineimages, $option["content"], $prefix . "\t\t\t");

							$html .= $prefix . "\t\t</td>\n";

							if (isset($option["padding"]))
							{
								if ($option["padding"][1] > 0)  $html .= $prefix . "\t\t<td style=\"padding: 0; margin: 0; font-size: 0; line-height: 0; width: " . (int)$option["padding"][1] . "px; height: 1px;\" width=\"" . (int)$option["padding"][1] . "\" height=\"1\"></td>\n";
							}
							else if (isset($option["width"]) && (!isset($option["row-align"]) || $option["row-align"] !== "right"))
							{
								$html .= $prefix . "\t\t<td style=\"padding: 0; margin: 0; font-size: 0; line-height: 0; height: 1px;\" height=\"1\"></td>\n";
							}

							$html .= $prefix . "\t</tr>\n";

							if (isset($option["padding"]) && $option["padding"][2] > 0)
							{
								$html .= $prefix . "\t<tr>";
								$html .= "<td colspan=\"" . $numcols . "\" style=\"padding: 0; margin: 0; font-size: 0; line-height: 0; height: " . (int)$option["padding"][2] . "px;\" height=\"" . (int)$option["padding"][2] . "\"></td>";
								$html .= "</tr>\n";
							}

							$html .= $prefix . "</table>\n";

							break;
						}
						case "space":
						case "split":
						{
							if (!isset($option["height"]))  $option["height"] = ($option["type"] === "space" ? 5 : 2);

							$html .= $prefix . "<table style=\"padding: 0; margin: 0; width: 100%;\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\"><tr>";
							$html .= "<td style=\"padding: 0; margin: 0; font-size: 0; line-height: 0; height: " . (int)$option["height"] . "px;" . (isset($option["bgcolor"]) ? " background-color: " . htmlspecialchars($option["bgcolor"]) . ";" : "") . "\" height=\"" . (int)$option["height"] . "\"" . (isset($option["bgcolor"]) ? " bgcolor=\"" . htmlspecialchars($option["bgcolor"]) . "\"" : "") . "></td>";
							$html .= "</tr></table>\n";

							break;
						}
						case "button":
						{
							if (!isset($option["bgcolor"]))  $option["bgcolor"] = "#4E88C2";
							if (!isset($option["border-radius"]))  $option["border-radius"] = 4;

							if (!isset($option["padding"]))  $option["padding"] = array(12);
							if (!is_array($option["padding"]))  $option["padding"] = array($option["padding"]);

							if (count($option["padding"]) == 1)  $option["padding"] = array($option["padding"][0], $option["padding"][0], $option["padding"][0], $option["padding"][0]);
							else if (count($option["padding"]) == 2)  $option["padding"] = array($option["padding"][0], $option["padding"][1], $option["padding"][0], $option["padding"][1]);
							else if (count($option["padding"]) == 3)  $option["padding"] = array($option["padding"][0], $option["padding"][1], $option["padding"][2], $option["padding"][1]);

							$numcols = ($option["padding"][3] > 0 ? 1 : 0) + 1 + ($option["padding"][1] > 0 ? 1 : 0);

							$html .= $prefix . "<table style=\"padding: 0; margin: 0;\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">\n";

							if ($option["padding"][0] > 0)
							{
								$html .= $prefix . "\t<tr>";
								$html .= "<td style=\"padding: 0; margin: 0; font-size: 0; line-height: 0; height: " . (int)$option["padding"][0] . "px;\" height=\"" . (int)$option["padding"][0] . "\"></td>";
								$html .= "</tr>\n";
							}

							$html .= $prefix . "\t<tr>\n";

							$style = "background-color: " . htmlspecialchars($option["bgcolor"]) . ";";
							if ($option["border-radius"] > 0)  $style .= " border-radius: " . (int)$option["border-radius"] . "px;";
							$style .= " border-top: " . (int)$option["padding"][0] . "px solid " . htmlspecialchars($option["bgcolor"]) . ";";
							$style .= " border-right: " . (int)$option["padding"][1] . "px solid " . htmlspecialchars($option["bgcolor"]) . ";";
							$style .= " border-bottom: " . (int)$option["padding"][2] . "px solid " . htmlspecialchars($option["bgcolor"]) . ";";
							$style .= " border-left: " . (int)$option["padding"][3] . "px solid " . htmlspecialchars($option["bgcolor"]) . ";";

							$html .= $prefix . "\t\t<td><a href=\"" . htmlspecialchars($option["href"]) . "\"" . (isset($option["target"]) ? " target=\"" . htmlspecialchars($option["target"]) . "\"" : "") . " style=\"" . $style . (isset($option["style"]) ? " " . htmlspecialchars($option["style"]) : "") . "\"" . (isset($option["id"]) ? " id=\"" . htmlspecialchars($option["id"]) . "\"" : "") . (isset($option["class"]) ? " class=\"" . htmlspecialchars($option["class"]) . "\"" : "") . ">" . (isset($option["text"]) ? htmlspecialchars($option["text"]) : "") . (isset($option["html"]) ? $option["html"] : "") . "</a></td>\n";

							$html .= $prefix . "\t</tr>\n";

							if ($option["padding"][2] > 0)
							{
								$html .= $prefix . "\t<tr>";
								$html .= "<td style=\"padding: 0; margin: 0; font-size: 0; line-height: 0; height: " . (int)$option["padding"][2] . "px;\" height=\"" . (int)$option["padding"][2] . "\"></td>";
								$html .= "</tr>\n";
							}

							$html .= $prefix . "</table>\n";

							break;
						}
						case "image":
						{
							// Inline image.
							if (isset($option["file"]))
							{
								$filename = str_replace("\\", "/", $option["file"]);
								$data = file_get_contents($filename);

								$pos = strrpos($filename, ".");
								if ($pos === false)  break;

								$fileext = strtolower(substr($filename, $pos + 1));

								$fileextmap = array(
									"jpg" => array("image/jpeg", "jpg"),
									"jpeg" => array("image/jpeg", "jpg"),
									"png" => array("image/png", "png"),
									"gif" => array("image/gif", "gif"),
								);

								if (!isset($fileextmap[$fileext]))  break;

								$name = "image" . sprintf("%03d", count($inlineimages) + 1) . "." . $fileextmap[$fileext][1];
								$cid = $name . "@" . strtoupper(dechex(time())) . "." . strtoupper(dechex(filemtime($filename)));

								$inlineimages[] = array(
									"type" => $fileextmap[$fileext][0],
									"name" => $name,
									"cid" => $cid,
									"data" => $data
								);

								$option["src"] = "cid:" . $cid;
							}

							$html .= $prefix . "<img src=\"" . htmlspecialchars($option["src"]) . "\" style=\"display: block; max-width: 100%;" . (isset($option["style"]) ? " " . htmlspecialchars($option["style"]) : "") . "\"" . (isset($option["id"]) ? " id=\"" . htmlspecialchars($option["id"]) . "\"" : "") . (isset($option["class"]) ? " class=\"" . htmlspecialchars($option["class"]) . "\"" : "") . (isset($option["alt"]) ? " alt=\"" . htmlspecialchars($option["alt"]) . "\"" : "") . (isset($option["width"]) ? " width=\"" . htmlspecialchars($option["width"]) . "\"" : "") . (isset($option["height"]) ? " height=\"" . htmlspecialchars($option["height"]) . "\"" : "") . " border=\"0\">\n";

							break;
						}
					}
				}
			}

			self::Finalize($html, $contentopts);
		}

		public static function Generate($styles, $contentopts)
		{
			if (!isset(self::$handlers) || !is_array(self::$handlers))  self::$handlers = array("init" => array(), "custom_type" => array(), "finalize" => array());

			// Generate the HTML.
			$html = <<<EOF
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta http-equiv="x-ua-compatible" content="ie=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
</head>
<body style="padding: 0px; margin: 0px;">

EOF;

			$inlineimages = array();

			self::ProcessContentOpts($html, $inlineimages, $contentopts, "");

			$html .= <<<EOF
</body>
</html>
EOF;

			// Inline styles.
			if (!class_exists("TagFilter", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/tag_filter.php";

			$htmloptions = TagFilter::GetHTMLOptions();
			$html2 = TagFilter::Explode($html, $htmloptions);
			$root = $html2->Get();

			foreach ($styles as $key => $val)
			{
				$rows = $root->Find($key);
//echo "Found " . count($rows) . " references to '" . $key . "'.\n";
				foreach ($rows as $row)
				{
					if (isset($row->style))  $row->style = trim($row->style) . (substr(trim($row->style), -1) !== ";" ? "; " : " ") . $val;
					else  $row->style = $val;
				}
			}

			// Remove 'id' and 'class' attributes.
			$rows = $root->Find('[id], [class]');
			foreach ($rows as $row)
			{
				unset($row->id);
				unset($row->class);
			}

			$html = $root->GetOuterHTML();

			return array("success" => true, "html" => $html, "inlineimages" => $inlineimages);
		}
	}
?>