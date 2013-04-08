<?php
/**
 * JFusion user Class for xenforo
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
 * JFusion user Class for xenforo
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
class JFusionUser_xenforo extends JFusionUser
{

	var $params;

	var $helper;

	/**
	 * Constructor function
	 */
	function JFusionUser_xenforo()
	{
		// Get the params object
		$this->params =& JFusionFactory::getParams($this->getJname());
	}

	/**
	 * Returns the name of this JFusion plugin
	 *
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'xenforo';
	}

	/**
	 * Get user
	 *
	 * @param   object  $userinfo         User info
	 * @param   string  $identifier_type  Not sure - seems not to be used here anyway
	 * @param   number  $ignore_id        Not sure - seems not to be used here anyway
	 *
	 * @return object
	 */
	function &getUser($userinfo, $identifier_type = 'auto', $ignore_id = 0)
	{
		// Get user info from database
		$db =& JFusionFactory::getDatabase($this->getJname());
		$helper = & JFusionFactory::getHelper($this->getJname());

		/* Default List Of Attributes Required
		 a.uid as userid, a.username, a.usergroup, a.username as name, a.email,
		a.password, a.salt as password_salt, a.usergroup as activation,
		b.isbannedgroup as block
		*/

		$query  = 'SELECT u.user_id AS userid, u.username AS name, u.username, u.is_banned as block, a.remember_key, ';
		$query .= 'u.email, u.user_group_id AS group_id, a.data AS authinfo, u.user_state, a.scheme_class, ';
		$query .= '"" AS activation, "" AS reason, "" AS user_type, u.secondary_group_ids AS membergroupids';
		$query .= ' FROM xf_user u INNER JOIN xf_user_authenticate a ON u.user_id = a.user_id ';
		if (is_object($userinfo))
		{
			$query .= " WHERE (LOWER(u.username) = LOWER('{$userinfo->username}')) OR (LOWER(u.email) = LOWER('{$userinfo->email}'))";
		}
		else
		{
			$query .= " WHERE (LOWER(u.username) = LOWER('{$userinfo}')) OR (LOWER(u.email) = LOWER('{$userinfo}'))";
		}

		$db->setQuery($query);
		$result = $db->loadObject();

		if ($result)
		{
			$authinfo = unserialize($result->authinfo);

			switch ($result->scheme_class)
			{
				case 'XenForo_Authentication_Core':
					$result->password = $authinfo['hash'];
					if (array_key_exists('salt', $authinfo))
					{
						$result->password_salt = $authinfo['salt'];
					}
					else
					{
						$result->password_salt = '';
					}
					$result->hashFunc = $authinfo['hashFunc'];
					break;
				case 'XenForo_Authentication_vBulletin':
					$result->password = $authinfo['hash'];
					$result->password_salt = $authinfo['salt'];
					break;
				default:
					break;
			}

			$query  = "SELECT title, user_title ";
			$query .= " FROM xf_user_group g LEFT JOIN xf_user_group_relation r ON g.user_group_id = r.user_group_id";
			$query .= " WHERE r.user_id = {$result->userid} AND r.is_primary = 1";

			$db->setQuery($query);
			$groupinfo = $db->loadObject();

			$result->group_name = (empty($groupinfo->user_title))?$groupinfo->title:$groupinfo->user_title;

			// Check to see if they are banned

			/*$query = 'SELECT userid FROM #__userban WHERE userid='. $result->userid;
			 $db->setQuery($query);
			if ($db->loadObject() || ($this->params->get('block_coppa_users', 1) && (int) $result->group_id == 4)) {
			$result->block = 1;
			} else {
			$result->block = 0;
			}*/

			// Check to see if the user is awaiting activation
			if ($result->user_state === 'valid')
			{
				$result->activation = '';
			}
			else
			{
				$helper->generateRandomString(32);
			}
		}
		return $result;
	}

	/**
	 * Delete the user
	 *
	 * @param   object  $userinfo  User info
	 *
	 * @return  array  Status
	 */
	function deleteUser($userinfo)
	{
		// TODO: create a function that deletes a user
		return array();

	}

	/**
	 * Destroy the session
	 *
	 * @param   object  $userinfo  User info
	 * @param   object  $options   Options
	 *
	 * @return  array
	 */
	function destroySession($userinfo, $options)
	{
		$helper =& JFusionFactory::getHelper($this->getJname());
		$helper->deleteSession();
		$status['error'] = false;
		return $status;
	}

	/**
	 * Create user session
	 *
	 * @param   object  $userinfo  User info
	 * @param   object  $options   Options
	 *
	 * @return string
	 */
	function createSession($userinfo, $options)
	{
		$status = array();
		$status['error'] = array();
		$status['debug'] = array();
		$status['debug'][] = "Plugin XenForo:: Entering user->createSession";
		$expires = 60 * 60 * 24 * 365;

		$params = JFusionFactory::getParams($this->getJname());
		$helper = & JFusionFactory::getHelper($this->getJname());

		// Do not create sessions for blocked users
		if (!empty($userinfo->block) || !empty($userinfo->activation))
		{
			$status['error'][] = JText::_('FUSION_BLOCKED_USER');
			$status['debug'][] = "Plugin XenForo:: Leaving user->createSession in error";
			return $status;
		}

		// Delete any existing session
		$result = $helper->deleteSession();

		$status['debug'] = array_merge($status['debug'], $result['debug']);
		$status['error'] = array_merge($status['error'], $result['error']);
		$result = $helper->createSession($userinfo->userid, $expires, $userinfo->remember_key);
		$status['debug'] = array_merge($status['debug'], $result['debug']);
		$status['error'] = array_merge($status['error'], $result['error']);

		// Was: $status['debug'][] = JText::_('NAME') . '=' . $name . ', ' . JText::_('VALUE') . '=' . substr($value, 0, 6) . '********, ' . JText::_('COOKIE_PATH') . '=' . $cookiepath . ', ' . JText::_('COOKIE_DOMAIN') . '=' . $cookiedomain;
		$status['debug'][] = "Plugin XenForo:: Leaving user->createSession";
		return $status;
	}

	/**
	 * Filter user name
	 *
	 * @param   string  $username  User name
	 *
	 * @return  string
	 */
	function filterUsername($username)
	{
		$helper =& JFusionFactory::getHelper($this->getJname());
		return $helper->filterUsername($username);
	}

	/**
	 * Block the user
	 *
	 * @param   object  $userinfo       User info
	 * @param   object  &$existinguser  Existing user - todo - what's the difference between the two users?
	 * @param   array   &$status        Status
	 *
	 * @return  void
	 */
	function blockUser($userinfo, &$existinguser, &$status)
	{
		$db = JFusionFactory::getDatabase($this->getJname());
		$user = new stdClass;
		$user->user_id = $existinguser->userid;
		$user->ban_user_id = 1;
		$user->ban_date = time();
		$user->end_date = 0;
		$user->user_reason = 'JFusion';

		// Now append the new user data
		if (!$db->insertObject('xf_user_ban', $user))
		{
			// Return the error
			$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
			return;
		}
		// Change user status
		$query = "UPDATE xf_user SET is_banned = 1
		WHERE user_id = {$existinguser->userid}";
		$db->setQuery($query);
		if (!$db->query())
		{
			// Return the error
			$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
			return;
		}
		$status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	}

	/**
	 * Unblock user
	 *
	 * @param   object  $userinfo       User info
	 * @param   object  &$existinguser  Existing user
	 * @param   array   &$status        Status
	 *
	 * @return  void
	 */
	function unblockUser($userinfo, &$existinguser, &$status)
	{
		$db = JFusionFactory::getDatabase($this->getJname());

		// Delete the ban
		$query = "DELETE FROM xf_user_ban
		WHERE user_id = {$existinguser->userid}";
		$db->setQuery($query);
		if (!$db->query())
		{
			// Return the error
			$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
			return;
		}
		$query = "UPDATE xf_user SET is_banned = 0
		WHERE user_id = {$existinguser->userid}";
		$db->setQuery($query);
		if (!$db->query())
		{
			// Return the error
			$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
			return;
		}
		$status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	}

	/**
	 * Update password
	 *
	 * @param   object  $userinfo       User info
	 * @param   object  &$existinguser  Existing user
	 * @param   array   &$status        Status
	 *
	 * @return  void
	 */
	function updatePassword($userinfo, &$existinguser, &$status)
	{
		$db = JFusionFactory::getDatabase($this->getJname());
		require_once JPATH_ADMINISTRATOR . '/components/com_jfusion/models/model.factory.php';
		$JFusionAuth = JFusionFactory::getAuth($this->getJname());

		$helper = & JFusionFactory::getHelper($this->getJname());
		$existinguser->remember_key = $helper->generateRandomString(40);
		$existinguser->password_salt = $helper->generateRandomString(64);

		$authinfo['salt'] = $existinguser->password_salt;
		$authinfo['hashFunc'] = 'sha256';
		$authinfo['hash'] = $JFusionAuth->generateEncryptedPassword($existinguser);
		$data = $db->getEscaped(serialize($authinfo));

		// Store updated password
		$query = "UPDATE xf_user_authenticate
		SET scheme_class = 'XenForo_Authentication_Core',
		data = '{$data}',
		remember_key = '{$existinguser->remember_key}'
		WHERE user_id = {$existinguser->userid}";

		$db->setQuery($query);
		if (!$db->query())
		{
			$status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $db->stderr();
		}
		else
		{
			$status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($authinfo['hash'], 0, 6) . '********';
		}
	}

	/**
	 * Create user
	 *
	 * @param   object  $userinfo  User info
	 * @param   array   &$status   Status
	 *
	 * @return  void
	 */
	function createUser($userinfo, &$status)
	{
		// Found out what usergroup should be used
		$db = JFusionFactory::getDatabase($this->getJname());
		$helper = & JFusionFactory::getHelper($this->getJname());

		$helper->createUser($userinfo);
		$status['debug'][] = JText::_('USER_CREATION');
		$status['userinfo'] = $this->getUser($userinfo);
		return;
	}

	/**
	 * Updates or creates a user for the integrated software. This allows JFusion to have external softwares as slave for user management
	 * $result['error'] (contains any error messages)
	 * $result['userinfo'] (contains the userinfo object of the integrated software user)
	 *
	 * @param   object  $userinfo   Contains the userinfo
	 * @param   int     $overwrite  Determines if the userinfo can be overwritten
	 *
	 * @return array result Array containing the result of the user update
	 */

	function updateUser($userinfo, $overwrite = 0)
	{
		$status = parent::updateUser($userinfo, $overwrite);
		if (empty($status['error']))
		{

			$userinfo->user_id = $userinfo->userid;
			if (!isset($userinfo->ipaddress))
			{
				$userinfo->ipaddress = '';
			}
			$existinguser = $this->getUser($userinfo);
			$helper =& JFusionFactory::getHelper($this->getJname());
			$helper->createUserProfile($userinfo, $existinguser, $status);

			// Update the user groups
			$params = JFusionFactory::getParams($this->getJname());
			$userGroups = $userinfo->groups;
			foreach ($userGroups as $gid)
			{
				$helper->changeUserGroup($existinguser->userid, $gid, JText::_('ACTIVATION_UPDATE_ERROR'), $status, true);
			}

		}
		return $status;
	}

	/**
	 * Update user's email address
	*
	* @param   object  $userinfo       User info
	* @param   object  &$existinguser  Existing user
	* @param   array   &$status        Status
	*
	* @return  void
	*/
	function updateEmail($userinfo, &$existinguser, &$status)
	{
		// We need to update the email
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = "UPDATE xf_user SET email ='{$userinfo->email}' WHERE user_id = {$existinguser->userid}";
		$db->setQuery($query);
		if (!$db->query())
		{
			$status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
		}
		else
		{
			$status['debug'][] = JText::_('PASSWORD_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
		}
	}

	/**
	 * Activate user
	 *
	 * @param   object  $userinfo       User info
	 * @param   object  &$existinguser  Existing user
	 * @param   array   &$status        Status
	 *
	 * @return  void
	 */
	function activateUser($userinfo, &$existinguser, &$status)
	{
		// Find user group used after activation
		$params = JFusionFactory::getParams($this->getJname());
		$helper = & JFusionFactory::getHelper($this->getJname());

		// Update the user
		$helper->changeUserGroup($existinguser->userid, $params->get('usergroup'), JText::_('ACTIVATION_UPDATE_ERROR'), $status, true);
		if (!isset($status['error']))
		{
			$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		}
	}

	/**
	 * Inactivate user
	 *
	 * @param   object  $userinfo       User info
	 * @param   object  &$existinguser  Existing user
	 * @param   array   &$status        Status
	 *
	 * @return  void
	 */
	function inactivateUser($userinfo, &$existinguser, &$status)
	{
		// Find the usergroup used for activation
		$params = JFusionFactory::getParams($this->getJname());
		$helper = & JFusionFactory::getHelper($this->getJname());

		// Update the user
		$helper->changeUserGroup($existinguser->userid, $params->get('usergroup'), JText::_('ACTIVATION_UPDATE_ERROR'), $status, false);
		if (!isset($status['error']))
		{
			$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		}
	}

	/**
	 * Sync user session
	 *
	 * @param   bool  $keepalive  Keep session alive
	 *
	 * @return number
	 */
	function syncSessions($keepalive = false)
	{
		$debug = (defined('DEBUG_SYSTEM_PLUGIN') ? true : false);
		if ($debug)
		{
			JError::raiseNotice('500', 'XenForo syncSessions called');
		}
		$helper =& JFusionFactory::getHelper($this->getJname());
		$params =& JFusionFactory::getParams($this->getJname());
		$options = array();
		$options['action'] = 'core.login.site';
		$expiry = 60 * 60 * 24 * 365;

		$JUser = & JFactory::getUser();

		// Do we have a Joomla persistant session ?
		if (JPluginHelper::isEnabled('system', 'remember'))
		{
			jimport('joomla.utilities.utility');
			$hash = JUtility::getHash('JLOGIN_REMEMBER');
			$joomla_persistant_cookie = JRequest::getString($hash, '', 'cookie', JREQUEST_ALLOWRAW | JREQUEST_NOTRIM);
		}
		else
		{
			$joomla_persistant_cookie = '';
		}

		if (!$JUser->get('guest', true))
		{
			// User logged into Joomla so check for active XenForo session
			if ($helper->persistantUser())
			{
				// We have a persistant cookie for XenForo
				// Lets check that the user's match
				$xenforo_user = (object) $helper->xenUserFromSession();
				if (isset($xenforo_user->email) && isset($xenforo_user->username))
				{
					if (($xenforo_user->email == $JUser->email) && ($xenforo_user->username == $JUser->username))
					{
						// Users match, so do nothing.  XenForo  auto login
						// will sort out the sessions.
					}
					else
					{
						// TODO User mismatch, terminate both sessions
						// for security reasons
					}
				}
				else
				{
					// Unknown XenForo user, do nothing
				}
			}
			else
			{
				// Do we have an active XenForo session ?
				if ($helper->sessionCookie())
				{
					// Is this a user session ?
					$xenuser = $helper->xenUserFromSession();

					if (empty($xenuser['user_id']))
					{
						// This is a Xenforo guest session

						// Log user into XenForo
						$userinfo = $helper->xenUserFromJUser($JUser);
						if (isset($userinfo['username']))
						{
							$helper->createSession($userinfo['userid'], $expiry, $userinfo['remember_key']);
						}
						else
						{
							// No matching user, so do nothing
						}
					}
					else
					{
						if (isset($xenuser->email) && isset($xenforo_user->username))
						{
							if (($xenuser->email == $JUser->email) && ($xenuser->username == $JUser->username))
							{
								// Users match, so do nothing.
								// We are already logged in
							}
							else
							{
								// TODO User mismatch, terminate both sessions
								// for security reasons
							}
						}
						else
						{
							// Unknown XenForo user, do nothing
						}
					}
				}
			}
		}
		else
		{
			// Not logged into Joomla
			if ($helper->persistantUser())
			{
				// Login to Joomla persistant
				// First identify the xenforo user
				$xenuser = (object) $helper->xenUserFromSession();

				// Verify that this is a user session
				if (!empty($xenuser->email) && !empty($xenuser->username))
				{
					// We have a XenForo user session, try to find matching Joomla user
					$JoomlaUser = JFusionFactory::getUser('joomla_int');
					$userinfo = $JoomlaUser->getUser($xenuser);
					if (!empty($userinfo))
					{
						// We have a valid Joomla user, so create user session.
						global $JFusionActivePlugin;
						$JFusionActivePlugin = $this->getJname();
						$options['remember'] = true;
						$status = $JoomlaUser->createSession($userinfo, $options);
						if ($debug)
						{
							JFusionFunction::raiseWarning('500', $status);
						}
						// No refresh needed
						return 0;
					}
					else
					{
						// No Joomla user, so lets create one.
						$status = array();
						$userinfo = $this->getUser($xenuser);
						JFusionJplugin::createUser($userinfo, $status, 'joomla_int');

						// $jfusion = new JFusionJplugin();
						// $result = $jfusion->createUser($userinfo, $status, 'joomla_int');

						// Now we have a Joomla user, lets create the Joomla session
						$JoomlaUser = JFusionFactory::getUser('joomla_int');
						$userinfo = $JoomlaUser->getUser($xenuser);
						if (!empty($userinfo))
						{
							header('Location: http://' . $_SERVER['HTTP_HOST']);
							exit(0);

							// We have a valid Joomla user, so create user session..

							/*global $JFusionActivePlugin;
							 $JFusionActivePlugin = $this->getJname();
							$status = $JoomlaUser->createSession($userinfo, $options);
							if ($debug) {
							JFusionFunction::raiseWarning('500',$status);
							}*/
						}
						return 0;
					}
				}

				// Just create the correct cookie and login
			}
			else
			{
				// Do we have an active XenForo session ?
				if ($helper->sessionCookie())
				{
					// Login to Joomla not persistant
					$xenuser = (object) $helper->xenUserFromSession();

					// Verify that this is a user session
					if (!empty($xenuser->email) && !empty($xenuser->username))
					{
						// We have a XenForo user session, try to find matching Joomla user
						$JoomlaUser = JFusionFactory::getUser('joomla_int');
						$userinfo = $JoomlaUser->getUser($xenuser);
						if (!empty($userinfo))
						{
							// We have a valid Joomla user, so create user session.
							global $JFusionActivePlugin;
							$JFusionActivePlugin = $this->getJname();

							$status = $JoomlaUser->createSession($userinfo, $options);
							if ($debug)
							{
								JFusionFunction::raiseWarning('500', $status);
							}
							// No refresh needed
							return 0;
						}
						else
						{
							// No Joomla user exists yet, so create one.
							$status = array();
							$userinfo = $this->getUser($xenuser);
							JFusionJplugin::createUser($userinfo, $status, 'joomla_int');

							// $jfusion = new JFusionJplugin();
							// $result = $jfusion->createUser($userinfo, $status, 'joomla_int');

							// Now we have a Joomla user, lets create the Joomla session
							$JoomlaUser = JFusionFactory::getUser('joomla_int');
							$userinfo = $JoomlaUser->getUser($xenuser);
							if (!empty($userinfo))
							{
								header('Location: http://' . $_SERVER['HTTP_HOST']);
								exit(0);

								// We have a valid Joomla user, so create user session.

								/*global $JFusionActivePlugin;
								 $JFusionActivePlugin = $this->getJname();
								$status = $JoomlaUser->createSession($userinfo, $options);
								if ($debug) {
								JFusionFunction::raiseWarning('500',$status);
								}*/
							}
							return 0;
						}
					}
				}
				else
				{
					// Not logged into either app, do nothing
				}
			}
		}
		return 0;
	}

	function updateUsergroup($userinfo, &$existinguser, &$status)
	{

	}
}
