<?php
/**
 * File containing administrator function for the jfusion plugin
 *
 * PHP version 5
 * Originally part of phpbb3 plugin.
 * Modified by mac@martinc.me.uk to support xenforo
 * @category    JFusion
 * @package     JFusionPlugins
 * @subpackage  xenforo
 * @author      JFusion Team <webmaster@jfusion.org>
 * @copyright   2008 JFusion. All rights reserved.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link        http://www.jfusion.org
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Admin Class for xenforo
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category    JFusion
 * @package     JFusionPlugins
 * @subpackage  xenforo
 * @author      JFusion Team <webmaster@jfusion.org>
 * @copyright   2008 JFusion. All rights reserved.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link        http://www.jfusion.org
 * @since       3.0
*/

class JFusionAdmin_xenforo extends JFusionAdmin
{
	/**
	 * returns the name of this JFusion plugin
	 *
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'xenforo';
	}

	/**
	 * Get the user table name
	 *
	 * @return string
	 */
	function getTablename()
	{
		return 'user';
	}

	/**
	 * Set up from path
	 *
	 * @param   string  $forumPath  Forum path
	 *
	 * @return multitype:NULL string unknown
	 */
	function setupFromPath($forumPath)
	{
		// Check for trailing slash and generate config file path
		if (substr($forumPath, -1) != '/')
		{
			$forumPath .= '/';
		}
		$myfile = $forumPath . 'library' . DS . 'config.php';

		// Include config file
		require_once $myfile;
		$xenforoApplication = file_get_contents($forumPath . 'library/XenForo/Application.php');

		$regex = '/.+globalSalt\'\s+=>\s+\'(.+?)\'.+[\r\n]+/s';
		if (preg_match($regex, $xenforoApplication, $matches))
		{
			$xenForoGlobalSalt = $matches[1];
		}

		// Save the parameters into the standard JFusion params format
		$params = array();
		if (isset($config['db']))
		{
			if (isset($config['db']['adapter']))
			{
				$params['database_type'] = $config['db']['adapter'];
			}
			else
			{
				$params['database_type'] = 'mysqli';
			}
			$params['database_host'] = $config['db']['host'];
			$params['database_user'] = $config['db']['username'];
			$params['database_password'] = $config['db']['password'];
			$params['database_name'] = $config['db']['dbname'];
		}
		$params['database_prefix'] = 'xf_';
		$params['source_path'] = $forumPath;

		$options = array('driver' => $params['database_type'],
				'host' => $params['database_host'],
				'user' => $params['database_user'],
				'password' => $params['database_password'],
				'database' => $params['database_name'],
				'prefix' => $params['database_prefix']);
		$bb = & JDatabase::getInstance($options);

		$jdb = JFusionFactory::getDatabase('joomla_int');

		// Enable the System - JFusion plugin
		$jfparams = $jdb->Quote(sprintf("syncsessions=1\nkeepalive=1\nsynclanguage=0\ndebug=0\n"));
		if (JFusionFunction::isJoomlaVersion('1.6'))
		{
			$query = "UPDATE #__extensions
			SET enabled = 1, params = {$jfparams}
			WHERE folder = 'system'
			AND   element = 'jfusion'
			AND type='plugin'";
		}
		else
		{
			$query = "UPDATE #__plugins
			SET published = 1, params = {$jfparams}
			WHERE folder = 'system'
			AND   element = 'jfusion'";
		}
		$jdb->setQuery($query);
		if (!$jdb->query())
		{
			// There was an error
			JError::raiseWarning(0, $jdb->stderr());
		}

		if (isset($config['cookie']))
		{
			if (isset($config['cookie']['domain']))
			{
				$params['cookie_domain'] = $config['cookie']['domain'];
			}
			else
			{
				$params['cookie_domain'] = '';
			}
			if (isset($config['cookie']['prefix']))
			{
				$params['cookie_prefix'] = $config['cookie']['prefix'];
			}
			else
			{
				$params['cookie_prefix'] = 'xf_';
			}
			if (isset($config['cookie']['path']))
			{
				$params['cookie_path'] = $config['cookie']['path'];
			}
			else
			{
				$params['cookie_path'] = '/';
			}
		}
		else
		{
			$params['cookie_domain'] = '';
			$params['cookie_prefix'] = 'xf_';
			$params['cookie_path']   = '/';
			$params['global_salt']   = $xenForoGlobalSalt;
		}
		if (isset($config['globalSalt']))
		{
			$params['global_salt'] = $config['globalSalt'];
		}
		else
		{
			$params['global_salt'] = $xenForoGlobalSalt;
		}
		return $params;
	}

	/**
	 * Get user list
	 *
	 * @return array
	 */
	function getUserList()
	{
		// Getting the connection to the db
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'SELECT username, email from xf_user';
		$db->setQuery($query);
		$userlist = $db->loadObjectList();
		return $userlist;
	}

	/**
	 * Get user count
	 *
	 * @return  int
	 */
	function getUserCount()
	{
		// Getting the connection to the db
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'SELECT count(*) from xf_user';
		$db->setQuery($query);

		// Getting the results
		return $db->loadResult();
	}

	/**
	 * Get list of user groups
	 *
	 * @return  array
	 */
	function getUsergroupList()
	{
		// Getting the connection to the db
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'SELECT user_group_id as id, title as name FROM xf_user_group';
		$db->setQuery($query);

		// Getting the results
		return $db->loadObjectList();
	}

	/**
	 * Get the default user group
	 *
	 * @return  object
	 */
	function getDefaultUsergroup()
	{
		$params = JFusionFactory::getParams($this->getJname());

		$usergroup_id = $params->get('usergroup');

		// We want to output the usergroup name
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'SELECT title from xf_user_group WHERE user_group_id = ' . (int) $usergroup_id;
		$db->setQuery($query);
		return $db->loadResult();
	}

	/**
	 * Is registration allowed?
	 *
	 * @return boolean
	 */
	function allowRegistration()
	{
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT option_value FROM xf_option  WHERE option_id ='registrationSetup'";
		$db->setQuery($query);
		$registrationSetup = unserialize($db->loadResult());
		if (intval($registrationSetup['enabled']) === 1)
		{
			$result = true;
			return $result;
		}
		else
		{
			$result = false;
			return $result;
		}
	}

	/**
     * Get an usergroup element
     *
     * @param   string  $name          Name of element
     * @param   string  $value         Value of element
     * @param   string  $node          Node of element
     * @param   string  $control_name  Name of controler
     *
     * @return string html
     */
	function usergroup($name, $value, $node, $control_name)
	{
		$jname = $this->getJname();

		// Get the master plugin to be throughout
		$master = JFusionFunction::getMaster();
		$advanced = 0;

		// Detect is value is a serialized array
		if (substr($value, 0, 2) == 'a:')
		{
			$value = unserialize($value);

			// Use advanced only if this plugin is not set as master
			if ($master->name != $this->getJname())
			{
				$advanced = 1;
			}
		}
		if (JFusionFunction::validPlugin($this->getJname()))
		{
			$usergroups = $this->getUsergroupList();
			foreach ($usergroups as $group)
			{
				$g[] = $group->name;
			}
			$comma_separated = implode(",", $g);
			$simple_value = $value;
			if (is_array($simple_value))
			{
				$simple_value = $comma_separated;
			}
			if (!empty($usergroups))
			{
				$simple_usergroup = "<table style=\"width:100%; border:0\">";
				$simple_usergroup .= '<tr><td>' . JText::_('DEFAULT_USERGROUP') . '</td><td><input type="text" name="' . $control_name . '[' . $name . ']" value="' . $simple_value . '" class="inputbox" /></td></tr>';
				$simple_usergroup .= "</table>";
			}
			else
			{
				$simple_usergroup = '';
			}
		}
		else
		{
			return JText::_('SAVE_CONFIG_FIRST');
		}
		// Check to see if current plugin is a slave
		$db = & JFactory::getDBO();
		$query = 'SELECT slave FROM #__jfusion WHERE name = ' . $db->Quote($jname);
		$db->setQuery($query);
		$slave = $db->loadResult();
		$list_box = '<select onchange="usergroupSelect(this.selectedIndex);">';
		if ($advanced == 1)
		{
			$list_box .= '<option value="0" selected="selected">Simple</option>';
		}
		else
		{
			$list_box .= '<option value="0">Simple</option>';
		}
		if ($slave == 1)
		{
			// Allow usergroup sync
			if ($advanced == 1)
			{
				$list_box .= '<option selected="selected" value="1">Avanced</option>';
			}
			else
			{
				$list_box .= '<option value="1">Avanced</option>';
			}
			// Prepare the advanced options
			$JFusionMaster = JFusionFactory::getAdmin($master->name);
			$master_usergroups = $JFusionMaster->getUsergroupList();
			$advanced_usergroup = "<table class=\"usergroups\">";
			if ($advanced == 1)
			{
				foreach ($master_usergroups as $master_usergroup)
				{
					$advanced_usergroup .= "<tr><td>" . $master_usergroup->name . '</td>';
					$advanced_usergroup .= '<td><input type="text" name="' . $control_name . '[' . $name . '][' . $master_usergroup->id . ']" value="' . $value[$master_usergroup->id] . '" class="inputbox" /></td></tr>';
				}
			}
			else
			{
				foreach ($master_usergroups as $master_usergroup)
				{
					$advanced_usergroup .= "<tr><td>" . $master_usergroup->name . '</td>';
					$advanced_usergroup .= '<td><input type="text" name="' . $control_name . '[' . $name . '][' . $master_usergroup->id . ']" value="' . $comma_separated . '" class="inputbox" /></td></tr>';
				}
			}
			$advanced_usergroup .= "</table>";
		}
		else
		{
			$advanced_usergroup = '';
		}
		$list_box .= '</select>';
		?>
<script Language="JavaScript">
        function usergroupSelect(option)
        {
            var myArray = new Array();
            myArray[0] = '<?php echo $simple_usergroup; ?>';
            myArray[1] = '<?php echo $advanced_usergroup; ?>';
            document.getElementById("JFusionUsergroup").innerHTML = myArray[option];
            }
        </script>
<?php

		if ($advanced == 1)
		{
			return JText::_('USERGROUP') . ' ' . JText::_('MODE') . ': ' . $list_box . '<br/><div id="JFusionUsergroup">' . $advanced_usergroup . '</div>';
		}
			else
		{
			return JText::_('USERGROUP') . ' ' . JText::_('MODE') . ': ' . $list_box . '<br/><div id="JFusionUsergroup">' . $simple_usergroup . '</div>';
			}
	}

}
