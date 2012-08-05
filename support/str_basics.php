<?php
	// CubicleSoft Basic PHP String processing functions.
	// (C) 2011 CubicleSoft.  All Rights Reserved.

	function ProcPOSTStr($data)
	{
		$data = trim($data);
		if (get_magic_quotes_gpc())  $data = stripslashes($data);

		return $data;
	}

	function ProcessSingleInput($data)
	{
		foreach ($data as $key => $val)
		{
			if (is_string($val))  $_REQUEST[$key] = ProcPOSTStr($val);
			else if (is_array($val))
			{
				$_REQUEST[$key] = array();
				foreach ($val as $key2 => $val2)  $_REQUEST[$key][$key2] = ProcPOSTStr($val2);
			}
			else  $_REQUEST[$key] = $val;
		}
	}

	// Cleans up all PHP input issues so that $_REQUEST may be used as expected.
	function ProcessAllInput()
	{
		ProcessSingleInput($_COOKIE);
		ProcessSingleInput($_GET);
		ProcessSingleInput($_POST);
	}

	function ExtractPathname($dirfile)
	{
		$dirfile = str_replace("\\", "/", $dirfile);
		$pos = strrpos($dirfile, "/");
		if ($pos === false)  $dirfile = "";
		else  $dirfile = substr($dirfile, 0, $pos + 1);

		return $dirfile;
	}

	function ExtractFilename($dirfile)
	{
		$dirfile = str_replace("\\", "/", $dirfile);
		$pos = strrpos($dirfile, "/");
		if ($pos !== false)  $dirfile = substr($dirfile, $pos + 1);

		return $dirfile;
	}

	function ExtractFileExtension($dirfile)
	{
		$dirfile = ExtractFilename($dirfile);
		$pos = strrpos($dirfile, ".");
		if ($pos !== false)  $dirfile = substr($dirfile, $pos + 1);
		else  $dirfile = "";

		return $dirfile;
	}

	function ExtractFilenameNoExtension($dirfile)
	{
		$dirfile = ExtractFilename($dirfile);
		$pos = strrpos($dirfile, ".");
		if ($pos !== false)  $dirfile = substr($dirfile, 0, $pos);

		return $dirfile;
	}

	// Makes an input filename safe for use.
	// Allows a very limited number of characters through.
	function FilenameSafe($filename)
	{
		return preg_replace('/[_]+/', "_", preg_replace('/[^A-Za-z0-9_.\-]/', "_", $filename));
	}

	function ReplaceNewlines($replacewith, $data)
	{
		$result = str_replace("\r\n", "\n", $data);
		$result = str_replace("\r", "\n", $result);
		$result = str_replace("\n", $replacewith, $result);

		return $result;
	}

	function LineInput($data, &$pos)
	{
		$CR = ord("\r");
		$LF = ord("\n");

		$result = "";
		$y = strlen($data);
		if ($pos > $y)  $pos = $y;
		while ($pos < $y && ord($data[$pos]) != $CR && ord($data[$pos]) != $LF)
		{
			$result .= $data[$pos];
			$pos++;
		}
		if ($pos + 1 < $y && ord($data[$pos]) == $CR && ord($data[$pos + 1]) == $LF)  $pos++;
		if ($pos < $y)  $pos++;

		return $result;
	}
?>
