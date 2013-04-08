<?php
/**
 * JFusion public Class for xenforo
 *
 * @category    JFusion
 * @package     JFusionPlugins
 * @subpackage  xenforo
 * @author      JFusion Team <webmaster@jfusion.org>
 * @copyright   2008 JFusion. All rights reserved.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link        http://www.jfusion.org
 */

defined('_JEXEC') or die('Restricted access');
/**
 * JFusion public Class for xenforo
 *
 * @category    JFusion
 * @package     JFusionPlugins
 * @subpackage  xenforo
 * @author      Martin Cooper <mac@martinc.me.uk>
 * @copyright   2011 Martin Cooper. All rights reserved.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link        http://www.martinc.me.uk
 * @since       3.0
*/

class JFusionPublic_xenforo extends JFusionPublic
{
	/**
	 * Get the plugin name
	 *
	 * @return string
	 */
	function getJname()
	{
		return 'xenforo';
	}

	/**
	 * Get the registration url
	 *
	 * @return string
	 */
	function getRegistrationURL()
	{
		return 'index.php?login/';
	}

	/**
	 * Get the url that allows the user to retrieve their password
	 *
	 * @return string
	 */
	function getLostPasswordURL()
	{
		return 'index.php?lost-password/';
	}

	/**
	 * Get the url that allows the user to retrieve their user name
	 *
	 * @return string
	 */
	function getLostUsernameURL()
	{
		return '';
	}

	/************************************************
	 * Functions For JFusion Who's Online Module
	***********************************************/

	/**
	 * Get the query to obtain the on-line users
	 *
	 * @param   int  $limit  Max number of users to return
	 *
	 * @return string SQL query
	 */
	function getOnlineUserQuery($limit)
	{
		$limiter = (!empty($limit)) ? "LIMIT 0,$limit" : '';

		// Get a unix time from 5 mintues ago
		date_default_timezone_set('UTC');
		$active = strtotime("-5 minutes", time());
		$query = "SELECT DISTINCT u.user_id AS userid, u.username_clean AS username, u.username AS name, u.user_email as email FROM #__users AS u INNER JOIN #__sessions AS s ON u.user_id = s.session_user_id WHERE s.session_viewonline =1 AND  s.session_user_id != 1 AND s.session_time > $active $limiter";
		return $query;
	}

	/**
	 * Get the number of on-line guests
	 *
	 * @return int
	 */
	function getNumberOnlineGuests()
	{

		// Get a unix time from 5 mintues ago
		date_default_timezone_set('UTC');
		$active = strtotime("-5 minutes", time());
		$db = & JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT COUNT(DISTINCT(session_ip)) FROM #__sessions WHERE session_user_id = 1 AND session_time > $active";
		$db->setQuery($query);
		$result = $db->loadResult();
		return $result;
	}

	/**
	 * Get the number of on-line members
	 *
	 * @return  int
	 */
	function getNumberOnlineMembers()
	{
		// Get a unix time from 5 mintues ago
		date_default_timezone_set('UTC');
		$active = strtotime("-5 minutes", time());
		$db = & JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT COUNT(DISTINCT(session_user_id)) FROM #__sessions WHERE session_viewonline = 1 AND session_user_id != 1 AND session_time > $active";
		$db->setQuery($query);
		$result = $db->loadResult();
		return $result;
	}
}
