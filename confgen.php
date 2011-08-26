<?php

//*****************************************************************************
// Config files functions
//

define('CATEGORY_SERVER', 'S');
define('CATEGORY_WEB', 'W');
define('CATEGORY_POSTPROCESS', 'P');
define('CATEGORY_PPPARAMETERS', 'O');

class Option {
	var $name;
	var $caption;
	var $value;
	var $defvalue;
	var $description;
	var $enabled;
	var $template;
	var $select;
	var $modified;
	var $multiid;
	var $type;
}

class Section {
	var $name;
	var $key;
	var $multi;
	var $category;
	var $modified;
	var $options = array();
}

function ReadConfigTemplate($filename, $skipsections) {
	
	$config = array();
	$section = null;
	$description = '';
	$firstdescrline = '';
	
	if (!file_exists($filename))
	{
		trigger_error("File not exists $filename");
		return false;
	}
	
	$file_handle = fopen($filename, "rb");
	if ($file_handle == 0)
	{
		trigger_error("Could not open file $filename");
		return false;
	}
	while (!feof($file_handle) ) {
		$line = trim(fgets($file_handle));

		if (!strncmp($line, '### ', 4)) {
			$section = new Section();
			$section->name = trim(substr($line, 4, strlen($line) - 8));
			$description = '';
			if (!isset($skipsections) || !in_array($section->name, $skipsections)) {
				$config[$section->name] = $section;
			}
		} else if (!strncmp($line, '# ', 2) || $line == '#') {
			if ($description != '') {
				$description .= ' ';
			}
			$description .= trim(substr($line, 1, 1000));
			$lastchar = substr($description, strlen($description) - 1, 1);
			if ($lastchar == '.' && $firstdescrline == '')
				$firstdescrline = $description;			
			if (strpos(".;:", $lastchar) > -1 || $line == '#') {
				$description .= "\n";
			}
		} else if (strpos($line, '=')) {
			if (!$section)
			{
				// bad template file; create default section.
				$section = new Section();
				$section->name = 'OPTIONS';
				$description = '';
				$config[$section->name] = $section;
			}
		
			$option = new Option();
			$option->enabled = substr($line, 0, 1) != '#';
			$option->name = trim(substr($line, $option->enabled ? 0 : 1, strpos($line, '=') - ($option->enabled ? 0 : 1)));
			$option->caption = $option->name;
			$option->defvalue = trim(substr(strstr($line, '='), 1, 1000));
			$option->description = $description;

			$pstart = strrpos($firstdescrline, '(');
			$pend = strrpos($firstdescrline, ')');
			if ($pstart && $pend && $pend == strlen($firstdescrline) - 2) {
				$option->select = array();
				$paramstr = substr($firstdescrline, $pstart + 1, $pend - $pstart - 1);
				$params = explode(',', $paramstr);
				foreach ($params as $p) {
					$option->select[] = trim($p);
				}
			}

			if (strpos($option->name, '1.') > -1) {
				$section->multi = true;
			}

			if (!$section->multi || strpos($option->name, '1.') > -1) {
				$section->options[] = $option;
			}
			
			if ($section->multi) {
				$option->enabled = false;
				$option->template = true;
			}

			$description = '';
			$firstdescrline = '';
		} else {
			$description = '';
			$firstdescrline = '';
		}
	}
	fclose($file_handle);
	
	return $config;
}

function ReadConfigValues($filename) {

	$values = array();
	
	if (!file_exists($filename))
	{
		trigger_error("File not exists $filename");
		return false;
	}
	
	$file_handle = fopen($filename, "rb");
	if ($file_handle == 0)
	{
		trigger_error("Could not open file $filename");
		return false;
	}
	while (!feof($file_handle) ) {
		$line = trim(fgets($file_handle));

		if (strpos($line, '=')) {
			$option = new Option();
			$enabled = substr($line, 0, 1) != '#';
			if ($enabled) {
				$name = strtolower(trim(substr($line, 0, strpos($line, '='))));
				$value = trim(substr(strstr($line, '='), 1, 1000));
				$values[$name] = $value;
			}
		}
	}
	fclose($file_handle);
	
	return $values;
}

function MergeValues(&$config, &$values) {
	
	// copy values
	foreach ($config as $section) {
		if ($section->multi) {

			// multi sections (news-servers, scheduler)

			$subexists = true;
			for ($i = 1; $subexists; $i++) {
				$subexists = false;
				foreach ($section->options as $option) {
					if (strpos($option->name, '1.') > -1) {
						$name = str_replace('1', $i, $option->name);
						if (array_key_exists(strtolower($name), $values)) {
							$subexists = true;
							break;
						}
					}
				}
				if ($subexists) {
					foreach ($section->options as $option) {
						if ($option->template) {
							$name = str_replace('1', $i, $option->name);
							// copy option
							$newoption = clone $option;
							$newoption->name = $name;
							$newoption->caption = $name;
							$newoption->enabled = true;
							$newoption->template = false;
							$newoption->multiid = $i;
							$section->options[] = $newoption;
							if (array_key_exists(strtolower($name), $values)) {
								$newoption->value = $values[strtolower($name)];
							}
						}
					}
				}
			}
		} else {

			// simple sections

			foreach ($section->options as $option) {
				if (array_key_exists(strtolower($option->name), $values)) {
					$option->value = $values[strtolower($option->name)];
				}
			}
		}
	}
}

function LoadServerConfig(&$config, &$skipsections) {
	global $ServerConfigTemplate, $ServerConfigFile;

	if (!file_exists($ServerConfigTemplate)) {
		echo '<div class="block"><font color="red">ERROR:</font> Could not load server configuration template. File "'.$ServerConfigTemplate.'" not found. Check option "ServerConfigTemplate".</div>';
		return false;
	}

	$serverconfig = ReadConfigTemplate($ServerConfigTemplate, $skipsections);
	if (!$serverconfig) {
		echo '<div class="block"><font color="red">ERROR:</font> Could not load server configuration template ('.$ServerConfigTemplate.'). Check option "ServerConfigTemplate".</div>';
		return false;
	}

	if (!file_exists($ServerConfigFile)) {
		echo '<div class="block"><font color="red">ERROR:</font> Could not load server configuration file. File "'.$ServerConfigFile.'" not found. Check option "ServerConfigFile".</div>';
		return false;
	}

	$values = ReadConfigValues($ServerConfigFile);
	
	if (!$values) {
		echo '<div class="block"><font color="red">ERROR:</font> Could not load server configuration file ('.$ServerConfigFile.'). Check option "ServerConfigFile".</div>';
		return false;
	}
	
	// copy values
	MergeValues($serverconfig, $values);
	
	// merge sections to main config-array
	foreach ($serverconfig as $key => $section) {
		$section->category = CATEGORY_SERVER;
		$section->key = "S-$key";
		$config["S-$key"] = $section;
	}
	
	return true;
}

function LoadPostProcessConfig(&$config) {
	global $PostProcessConfigTemplate, $PostProcessConfigFile;

	if (!file_exists($PostProcessConfigTemplate)) {
		echo '<div class="block"><font color="red">ERROR:</font> Could not load postprocess configuration template. File "'.$PostProcessConfigTemplate.'" not found. Check option "PostProcessConfigTemplate".</div>';
		return false;
	}

	$postprocessconfig = ReadConfigTemplate($PostProcessConfigTemplate, null);
	if (!$postprocessconfig) {
		echo '<div class="block"><font color="red">ERROR:</font> Could not load postprocess configuration template ('.$PostProcessConfigTemplate.'). Check option "PostProcessConfigTemplate".</div>';
		return false;
	}

	if (!file_exists($PostProcessConfigFile)) {
		echo '<div class="block"><font color="red">ERROR:</font> Could not load postprocess configuration file. File "'.$PostProcessConfigFile.'" not found. Check option "PostProcessConfigFile".</div>';
		return false;
	}

	$values = ReadConfigValues($PostProcessConfigFile);
	
	if (!$values) {
		echo '<div class="block"><font color="red">ERROR:</font> Could not load postprocess configuration file ('.$PostProcessConfigFile.'). Check option "PostProcessConfigFile".</div>';
		return false;
	}
	
	// copy values
	MergeValues($postprocessconfig, $values);
	
	// merge sections to main config-array
	foreach ($postprocessconfig as $key => $section) {
		if ($section->name == 'POSTPROCESSING-PARAMETERS') {
			$section->category = CATEGORY_PPPARAMETERS;
		} else {
			$section->category = CATEGORY_POSTPROCESS;
		}
		$section->key = "P-$key";
		$config["P-$key"] = $section;
	}
	
	return true;
}

function LoadWebConfig(&$config) {
	global $WebConfigTemplate, $WebConfigFile;

	if (!file_exists($WebConfigTemplate)) {
		echo '<div class="block"><font color="red">ERROR:</font> Could not load web configuration template. File "'.$WebConfigTemplate.'" not found.</div>';
		return false;
	}

	$webconfig = ReadConfigTemplate($WebConfigTemplate, null);
	if (!$webconfig) {
		echo '<div class="block"><font color="red">ERROR:</font> Could not load web configuration template ('.$WebConfigTemplate.').</div>';
		return false;
	}

	if (!file_exists($WebConfigFile)) {
		// copy template file to config file
		if (!copy($WebConfigTemplate, $WebConfigFile)) {
			echo '<div class="block"><font color="red">ERROR:</font> Could not load web configuration file and could not create a new one. File "'.$WebConfigFile.'" not found.</div>';
			return false;
		}		
	}

	if (!file_exists($WebConfigFile)) {
		echo '<div class="block"><font color="red">ERROR:</font> Could not load web configuration file. File "'.$WebConfigFile.'" not found.</div>';
		return false;
	}

	$values = ReadConfigValues($WebConfigFile);
	
	if (!$values) {
		echo '<div class="block"><font color="red">ERROR:</font> Could not load web configuration file ('.$WebConfigFile.').</div>';
		return false;
	}

	// further processing of web-options
	foreach ($webconfig as $section) {
		foreach ($section->options as $option) {
			$option->name = $option->name;
			$option->caption = substr($option->caption, 1, strlen($option->caption) - 1);
			$option->description = str_replace('$', '', $option->description);
			$option->description = str_replace('NOTE: Backslashes (on Windows) must be doubled.', '', $option->description);
			$option->description = str_replace('\\\\', '\\', $option->description);
			$option->description = str_replace('(true, false)', '(yes, no)', $option->description);
			$option->description = trim($option->description);

			WebOptionConfigToValue($option, $option->defvalue);
		}
	}

	// copy values
	foreach ($webconfig as $section) {
		foreach ($section->options as $option) {
			if (array_key_exists(strtolower($option->name), $values)) {
				WebOptionConfigToValue($option, $values[strtolower($option->name)]);
			}
		}
	}

	// merge sections to main config-array
	foreach ($webconfig as $key => $section) {
		$section->category = CATEGORY_WEB;
		$section->key = "W-$key";
		$config["W-$key"] = $section;
	}

	return true;
}

function SaveServerConfig(&$config, $filename, $category) {
	
	$configcontent = array();

	// read config file
	$file_handle = fopen($filename, "rb");
	if ($file_handle == 0)
	{
		trigger_error("Could not open file $filename");
		return false;
	}
	while (!feof($file_handle) ) {
		$configcontent[] = fgets($file_handle);
	}
	fclose($file_handle);

	// apply settings
	foreach ($config as $section) {
		foreach ($section->options as $option) {
			if ($option->modified && ($section->category == $category)) {
				// find option in configcontent array
				$found = false;
				foreach ($configcontent as $key => $line) {
					if (strpos($line, '=') && strncmp($line, '# ', 2)) {
						$enabled = substr($line, 0, 1) != '#';
						$name = trim(substr($line, $enabled ? 0 : 1, strpos($line, '=') - ($enabled ? 0 : 1)));
						if (strcasecmp($name, $option->name) == 0) {
							$configcontent[$key] = $option->name.'='.$option->value."\n";
							$found = true;
							break;
						}
					}
				}
				
				if (!$found) {
					$configcontent[] = $option->name.'='.$option->value."\n";
				}			
			}
		}
	}
	
	// delete multi options not listed in current config
	foreach ($configcontent as $key => $line) {
		if (strpos($line, '=') && strncmp($line, '# ', 2)) {
			$enabled = substr($line, 0, 1) != '#';
			$name = trim(substr($line, $enabled ? 0 : 1, strpos($line, '=') - ($enabled ? 0 : 1)));

			if (strpos($name, '.') > -1) {
				$found = false;
				foreach ($config as $section) {
					if ($section->multi) {
						foreach ($section->options as $option) {
							if (!$option->template && strcasecmp($name, $option->name) == 0) {
								$found = true;
								break;
							}
						}
					}
					if ($found) {
						break;
					}
				}
				
				if (!$found) {
					unset($configcontent[$key]);
				}
			}
		}
	}
	
	// write config file
	$file_handle = fopen($filename, "w");
	if ($file_handle == 0)
	{
		trigger_error("Could not open file $filename for writing");
		return false;
	}
	foreach ($configcontent as $line) {
		fwrite($file_handle, $line);
	}
	fclose($file_handle);
	
	return true;
}

function SaveWebConfig(&$config, $filename) {
	
	$configcontent = array();

	// read config file
	$file_handle = fopen($filename, "rb");
	if ($file_handle == 0)
	{
		trigger_error("Could not open file $filename");
		return false;
	}
	while (!feof($file_handle) ) {
		$configcontent[] = fgets($file_handle);
	}
	fclose($file_handle);

	// remove closing php-tag
	foreach ($configcontent as $key => $line) {
		if (Trim($line) == '?>') {
			unset($configcontent[$key]);
		}
	}

	// apply settings
	foreach ($config as $section) {
		foreach ($section->options as $option) {
			if ($option->modified && ($section->category == CATEGORY_WEB)) {
				// find option in configcontent array
				$found = false;
				foreach ($configcontent as $key => $line) {
					if (strpos($line, '=') && strncmp($line, '# ', 2)) {
						$name = trim(substr($line, 0, strpos($line, '=')));
						if (strcasecmp($name, $option->name) == 0) {
							$value = WebOptionValueToConfig($option).';';
							$configcontent[$key] = $option->name.'='.$value."\n";
							$found = true;
							break;
						}
					}
				}
				
				if (!$found) {
					$value = WebOptionValueToConfig($option).';';
					$configcontent[] = $option->name.'='.$value."\n";
				}			
			}
		}
	}
	
	// add closing php-tag
	$configcontent[] ='?>';
	
	// write config file
	$file_handle = fopen($filename, "w");
	if ($file_handle == 0)
	{
		trigger_error("Could not open file $filename for writing");
		return false;
	}
	foreach ($configcontent as $line) {
		fwrite($file_handle, $line);
	}
	fclose($file_handle);
	
	return true;
}

function SaveConfig(&$config) {
	global $ServerConfigFile, $WebConfigFile, $PostProcessConfigFile;

	$server_modified = false;
	$web_modified = false;
	$postprocess_modified = false;
	
	foreach ($config as $section) {
		foreach ($section->options as $option) {
			if ($option->modified || $section->modified) {
				if ($section->category == CATEGORY_SERVER) {
					$server_modified = true;
				} else if ($section->category == CATEGORY_WEB) {
					$web_modified = true;
				} else if ($section->category == CATEGORY_POSTPROCESS) {
					$postprocess_modified = true;
				}
			}
		}
	}

	$OK = true;
	
	if ($server_modified) {
		$OK &= SaveServerConfig($config, $ServerConfigFile, CATEGORY_SERVER);
	}
	if ($web_modified) {
		$OK &= SaveWebConfig($config, $WebConfigFile);
	}
	if ($postprocess_modified) {
		$OK &= SaveServerConfig($config, $PostProcessConfigFile, CATEGORY_POSTPROCESS);
	}
	
	return $OK;	
}

function WebOptionValueToConfig(&$option) {

	global $ListOptions;

	$value = $option->value;
	
	if ($option->type == 'string') {
		$value = str_replace('\\', '\\\\', $value);
		$value = '\''.$value.'\'';
	} else if ($option->type == 'bool') {
		if ($value == 'yes') {
			$value = 'true';
		} else if ($value == 'no') {
			$value = 'false';
		}
	}

	// special handling for list-options ("Categories", etc.)
	if (in_array($option->name, $ListOptions)) {
		if (strncasecmp($value, 'array(', 6)) {
			if ($value != '') {
				$value = '\''.str_replace(',', '\',\'', $value).'\'';

				// normalizing spaces between commas
				$oldvalue = '';
				while ($oldvalue != $value) {
					$oldvalue = $value;
					$value = str_replace(',\' ', ', \'', $value);
					$value = str_replace(', \' ', ', \'', $value);
					$value = str_replace(' \',', '\',', $value);
					$value = str_replace(',\'', ', \'', $value);
					$value = str_replace('\' ,', '\',', $value);
				}
			}
			$value = 'array('.$value.')';
		}
	}
	
	return $value;
}

function WebOptionConfigToValue(&$option, $confvalue) {

	global $ListOptions;

	$value = $confvalue;
	$value = substr($value, 0, strlen($value) - 1);
	
	if (!strcasecmp($value, 'true') || !strcasecmp($value, 'false')) {
		if (!strcasecmp($value, 'true')) {
			$value = 'yes';
		} else if (!strcasecmp($value, 'false')) {
			$value = 'no';
		}
		$option->type = 'bool';
		// replace select (true, false) with (yes, no)
		$option->select = array('yes', 'no');
	} else if (substr($value, 0, 1) == '\'') {
		$value = substr($value, 1, strlen($value) - 2);
		$option->type = 'string';
		$value = str_replace('\\\\', '\\', $value);
	}

	// special handling for list-options ("Categories", etc.)
	if (in_array($option->name, $ListOptions)) {
		if (!strncasecmp($value, 'array(', 6)) {
			$value = substr($value, 6, strlen($value) - 6 - 1);
			$value = str_replace('\'', '', $value);
		}
	}

	$option->value = $value;
}

function MergeSettings(&$config, &$request) {
	
	foreach ($config as $section) {
		foreach ($section->options as $option) {
			if (!$option->template) {
				$name = str_replace('.', '_', $section->category.'-'.$option->name);
				if (isset($request[$name])) {
					$value = $request[$name];
					if ($option->value != $value) {
						$option->value = $value;
						$option->modified = true;
					}
				}
			}
		}
	}
}

function DeleteMultiSet(&$config, $secionname, $optionname, $deletemultiid) {

	$section = $config[$secionname];

	// delete set of options
	foreach ($section->options as $key => $option) {
		if ($option->multiid == $deletemultiid) {
			unset($section->options[$key]);
			$section->modified = true;
		}
	}
	
	// renumerate sets
	foreach ($section->options as $option) {
		if ($option->multiid > $deletemultiid) {
			$option->name = str_replace($option->multiid, $option->multiid-1, $option->name);
			$option->multiid--;
			$option->modified = true;
		}	
	}	
}

function AddMultiSet(&$config, $secionname, $optionname) {

	$section = $config[$secionname];

	// find the biggest multiid
	$maxmultiid = 0;
	foreach ($section->options as $option) {
		if ($maxmultiid < $option->multiid) {
			$maxmultiid = $option->multiid;
		}
	}
	$maxmultiid++;

	// add multi set
	foreach ($section->options as $option) {
		if ($option->template) {
			$name = str_replace('1', $maxmultiid, $option->name);
			// copy option
			$newoption = clone $option;
			$newoption->name = $name;
			$newoption->enabled = true;
			$newoption->template = false;
			$newoption->multiid = $maxmultiid;
			$newoption->value = $newoption->defvalue;
			$newoption->modified = true;
			$section->options[] = $newoption;
			$section->modified = true;
		}
	}
}


//*****************************************************************************
// HTML generation functions
//

function BuildOptionRaw(&$option, &$section) {
	echo ($option->enabled ? '<tr class="enabledoption">' : '<tr class="disabledoption">');
	
	echo '<td width="150">'.$option->caption.'&nbsp;&nbsp;</td>';
	echo '<td>';

	if (count($option->select) > 1) {
		echo '<select class="configselect" name="'.$section->category.'-'.$option->name.'">';
		$valfound = false;
		foreach($option->select as $pvalue) {
			if (strcasecmp($pvalue, $option->value) == 0) {
				echo "<option selected='selected'>$pvalue</option>";
				$valfound = true;
			} else {
				echo "<option>$pvalue</option>";
			}
		}
		if (!$valfound) {
			echo "<option selected='selected'>$option->value</option>";
		}
		echo '</select>';
	} else if (count($option->select) == 1) {
		echo '<input type="text" name="'.$section->category.'-'.$option->name.'" value="'.$option->value.'" class="configeditnumeric">';
		echo ' '.$option->select[0];
	} else if (!strncasecmp($option->description, 'User name', 9) ||
			   !strncasecmp($option->description, 'IP ', 3)) {
		echo '<input type="text" name="'.$section->category.'-'.$option->name.'" value="'.$option->value.'" class="configeditsmall">';
	} else if (!strncasecmp($option->description, 'Password', 8)) {
		echo '<input type="password" name="'.$section->category.'-'.$option->name.'" value="'.$option->value.'" class="configeditsmall">';
	} else {
		echo '<input type="text" name="'.$section->category.'-'.$option->name.'" value="'.$option->value.'" class="configeditlarge">';
	}

	echo '</td>';
	echo '</tr>';
	echo '<tr>';
	if ($option->description != '') {
		$htmldescr = $option->description;
		$htmldescr = str_replace("NOTE: do not forget to uncomment the next line.\n", '', $htmldescr);
		$htmldescr = htmlspecialchars($htmldescr);
		$htmldescr = str_replace("\n", '<br>', $htmldescr);
		$htmldescr = str_replace('NOTE: ', '<font color="red"><b>NOTE: </b></font>', $htmldescr);
		
		echo '<td></td>';
		echo '<td><table><tr><td><div class="description">'.$htmldescr.'</div></td></tr></table></td>';
		echo '</tr>';
	}
}

function BuildMultiRowStart(&$section, $multiid, &$option) {
	$name = $option->name;
	$setname = substr($name, 0, strpos($name, '.'));

	echo '<tr><td colspan="2"><a name="'.$setname.'"></td></tr>';
	echo '<tr><td colspan="2" class="configsettitle">'.$setname.'</td></tr>';
	echo '<tr><td colspan="2"></td></tr>';
	echo '<tr><td colspan="2"></td></tr>';
}

function BuildMultiRowEnd(&$section, $multiid, $hasmore, $hasoptions) {
	$name = $section->options[0]->name;
	$setname = substr($name, 0, strpos($name, '1'));
	
	if ($hasoptions) {
		echo '<tr><td colspan="2"><table class="tableline" width="100%"><tr><td></td></tr></table></td></tr>';
		echo '<tr><td colspan="2">';
		echo "<input type='button' value='Delete $setname$multiid' onclick='location=\"?delete=$setname&id=$multiid&section=$section->key\"'>";
		echo '</td></tr>';
		echo '<tr><td colspan="2">&nbsp;</td></tr>';
	}

	if (!$hasmore) {
		echo '<tr><td colspan="2"><table class="tableline" width="100%"><tr><td></td></tr></table></td></tr>';
		echo '<tr><td colspan="2">';
		$nextid = $hasoptions ? $multiid+1 : 1;
		echo "<input type='button' value='Add $setname' onclick='location=\"?add=$setname&id=$nextid&section=$section->key\"'>";
		echo '</td></tr>';
	}
}

function BuildOptionsContent(&$config, $reqsection, $sectionframe) {

	foreach ($config as $section) {
		if ($section->key == $reqsection || !$reqsection) {
			if ($sectionframe) {
				echo '<div class = "block"><center><b>'.$section->name.'</b></center><br>';
			}

			echo '<table width="100%">';
		
			$lastmultiid = 1;
			$firstmultioption = true;
			$hasoptions = false;
			foreach ($section->options as $option) {
				if (!$option->template) {
					if ($section->multi && $option->multiid != $lastmultiid) {
						// new set in multi section
						BuildMultiRowEnd($section, $lastmultiid, true, true);
						$lastmultiid = $option->multiid;
						$firstmultioption = true;
					}
					echo '<tr><td colspan="2"><table class="tableline" width="100%"><tr><td></td></tr></table></td></tr>';
					if ($section->multi && $firstmultioption) {
						BuildMultiRowStart($section, $option->multiid, $option);
						$firstmultioption = false;
					}
					BuildOptionRaw($option, $section);
					$hasoptions = true;
				}
			}
			if ($section->multi) {
				BuildMultiRowEnd($section, $lastmultiid, false, $hasoptions);
			}
			
			echo '</table>';
			if ($sectionframe) {
				echo '</div><br>';
			}
		}
	}
}

?>