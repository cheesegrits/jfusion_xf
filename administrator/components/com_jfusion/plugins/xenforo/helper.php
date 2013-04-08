<?php
// No direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion helper Class for xenforo
 *
 * @category    JFusion
 * @package     JFusionPlugins
 * @subpackage  xenforo
 * @author      Martin Cooper <mac@martinc.me.uk>
 * @copyright   2011 Martin Cooper. All rights reserved.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link        http://www.martinc.me.uk
*/
class JFusionHelper_xenforo
{
	var $params;
	var $cookie_path;
	var $cookie_domain;
	var $httponly;
	var $global_salt;
	var $cookie_prefix;
	var $cookie_persist;
	var $session_id;
	var $currenttime;
	var $ipaddress;
	var $db;

	private function deleteCookie($name, $httponly)
	{
		@setcookie($this->cookie_prefix . $name, '', $this->currenttime - 86400, $this->cookie_path, $this->cookie_domain, false, empty($httponly)?$this->httponly:$httponly);
	}

	private function setCookie($name, $value, $expires)
	{
		@setcookie($name, $value, $this->currenttime + $expires, $this->cookie_path, $this->cookie_domain, false, $this->httponly);
	}

	public function JFusionHelper_xenforo()
	{
		// Get params and any cookies that are available
		$this->params = & JFusionFactory::getParams($this->getJname());
		$this->cookie_prefix = $this->params->get('cookie_prefix', 'xf_');
		$this->db = JFusionFactory::getDatabase($this->getJname());
		$this->cookie_domain = $this->params->get('cookie_domain', '');
		$this->cookie_path = $this->params->get('cookie_path', '/');
		$this->httponly = is_array($_COOKIE)?false:true;
		$this->global_salt = $this->params->get('global_salt', '');

		// Do we need to verify ??
		$this->ipaddress = $_SERVER['REMOTE_ADDR'];
		$this->currenttime = time();

		$this->cookie_persist = $this->db->getEscaped(JRequest::getVar($this->cookie_prefix . 'user', '', 'cookie'));
		$this->session_id     = $this->db->getEscaped(JRequest::getVar($this->cookie_prefix . 'session', '', 'cookie'));
	}

	/**
	 * Get the plugin name
	 *
	 * @return string
	 */
	public function getJname()
	{
		return 'xenforo';
	}

	/**
	 * Filter user name
	 *
	 * @param   string  $username  User name
	 *
	 * @TODO Username filtering
	 *
	 * @return  string
	 */
	function filterUsername($username)
	{
		return $username;
	}

	/**
	 * Is there a persistant user (what ever that means)
	 *
	 * @return boolean
	 */
	public function persistantUser()
	{
		return empty($this->cookie_persist)? false:true;
	}

	/**
	 * Is there a session cookie
	 *
	 * @return boolean
	 */
	public function sessionCookie()
	{
		return empty($this->session_id)? false:true;
	}

	/**
	 * Generate random string
	 *
	 * @param   number  $length  String length
	 *
	 * @return string
	 */
	public function generateRandomString($length = 20)
	{
		$data = '';
		while (strlen($data) < $length)
		{
			$data .= md5(uniqid(mt_srand(time() - ip2long($this->ipaddress)), true));
		}
		return substr(md5($data), 0, $length);
	}

	/**
	 * Delete the session (presumably on logout)
	 *
	 * @return  array with debug and status keys
	 */
	public function deleteSession()
	{
		// Was: $status['debug'][] = JText::_('NAME') . '=' . $name . ', ' . JText::_('VALUE') . '=' . substr($value, 0, 6) . '********, ' . JText::_('COOKIE_PATH') . '=' . $cookiepath . ', ' . JText::_('COOKIE_DOMAIN') . '=' . $cookiedomain;
		$status['debug'][] = "Plugin XenForo:: entering function helper->deleteSession";

		// Do we have a session cookie?
		if (!empty($this->session_id))
		{
			// We have an existing session cookie, so lets delete the session
			// both from the database and the cookie
			$status['debug'][] = "Plugin XenForo::helper->deleteSession deleting session_id {$this->session_id} from database";

			$query = "DELETE FROM xf_session WHERE  session_id = '{$this->session_id}'";
			$this->db->setQuery($query);
			if (!$this->db->query())
			{
				// Ignore error, as row may not exist in session table
				$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $this->db->stderr();
			}
			$status['debug'][] = "Plugin XenForo::helper->deleteSession deleting XenForo session cookie {$this->session_id}";
			$this->deleteCookie('session', true);
		}

		// TODO Convert session to guest session rather than destroy maybe ??

		// If we have an existing user cookie, delete it
		if (!empty($this->cookie_persist))
		{
			$status['debug'][] = "Plugin XenForo::helper->deleteSession deleting XenForo user cookie {$this->cookie_persist}";
			$this->deleteCookie('user');
		}
		$status['debug'][] = "Plugin XenForo:: Leaving function helper->deleteSession";
		return $status;
	}

	/**
	 * Create user session
	 *
	 * @param   int   $userid        User id
	 * @param   int   $expiry        Cookie expiry time
	 * @param   bool  $remember_key  Remember the cookie
	 *
	 * @return  array with debug and status keys
	 */
	public function createSession($userid, $expiry, $remember_key = '')
	{
		$status['debug'][] = "Plugin XenForo:: entering function helper->createSession";

		// Create a new session ID
		$createNew = 1;
		if (!empty($this->session_id))
		{
			$query = "SELECT *
			FROM xf_session
			WHERE session_id = '{$this->session_id}'";
			$this->db->setQuery($query);
			$session = $this->db->loadObject();
			if (!empty($session))
			{
				// We have an existing session.
				$session_info = unserialize($session->session_data);
				if ($session_info['user_id'] == $userid)
				{
					// The session is for this user, so do nothing
					$createNew = 0;
				}
				else
				{
					// This is for someone else, so delete the session
					$this->deleteSession();

					// Create a new session ID
					$this->session_id = md5(uniqid(microtime(true), true));
				}
			}
			else
			{
				// This is a guest session, so delete it
				$this->deleteSession();
			}

		}

		if ($createNew)
		{
			$this->session_id = md5(uniqid(microtime(true), true));
			$status['debug'][] = "Plugin XenForo:: helper->createSession new session_id = {$this->session_id}";
			$session_info = array('sessionStart' => $this->currenttime,
					'ip' => (is_string($this->ipaddress) && strpos($this->ipaddress, '.'))?ip2long($this->ipaddress):false,
					'previousActivity' => 0,
					'user_id' => intval($userid)
			);

			// Insert session into database
			$session_data = $this->db->Quote(serialize($session_info));

			// Was: $expiry_time = $this->currenttime + $expiry;
			$expiry_time = time();
			$expiry_time += $expiry;
			$query = "INSERT INTO xf_session (session_id, session_data, expiry_date)
			VALUES ('{$this->session_id}', {$session_data}, {$expiry_time})";
			$status['debug'][] = "Plugin XenForo:: helper->createSession inserting session record into xf_session table";
			$this->db->setQuery($query);
			if (!$this->db->query())
			{
				// Return the error
				$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $this->db->stderr();
				$status['debug'][] = "Plugin XenForo:: helper->createSession exiting function in error";
				return $status;
			}

			// Set the session cookie
			$this->setCookie($this->cookie_prefix . 'session', $this->session_id, $expiry);
			$status['debug'][] = "Plugin XenForo:: helper->createSession setting session cookie";

			if (!empty($remember_key))
			{
				$status['debug'][] = "Plugin XenForo:: helper->createSession setting remember key ";

				// Generate the remember key
				$rememberKeyForCookie = intval($userid) . ',' . sha1($this->global_salt . $remember_key);
				$status['debug'][] = "Plugin XenForo:: helper->createSession setting remember key cookie to {$rememberKeyForCookie}";

				// Set the user cookie
				$this->setCookie($this->cookie_prefix . 'user', $rememberKeyForCookie, $expiry);
			}
		}
		// Was: $status['debug'][] = JText::_('NAME') . '=' . $name . ', ' . JText::_('VALUE') . '=' . substr($value, 0, 6) . '********, ' . JText::_('COOKIE_PATH') . '=' . $cookiepath . ', ' . JText::_('COOKIE_DOMAIN') . '=' . $cookiedomain;
		return $status;
	}

	/**
	 * Create a xen user from a Joomla user
	 *
	 * @param   JUser  &$JUser  Joomla user object
	 *
	 * @return multitype:NULL
	 */
	public function xenUserFromJUser(&$JUser)
	{
		$userinfo = array();
		if (!$JUser->get('guest', true))
		{
			$userinfo['email'] = $JUser->get('email', '');
			$userinfo['username'] = $JUser->get('username', '');

			$query = "SELECT u.user_id, a.remember_key
					FROM xf_user u
					LEFT JOIN xf_user_authenticate a ON u.user_id = a.user_id
					WHERE u.email = " . $this->db->Quote($userinfo['email']) . "
							AND u.username = " . $this->db->Quote($userinfo['username']);

			$this->db->setQuery($query);
			$user = $this->db->loadObject();
			$userinfo['userid'] = $user->user_id;
			$userinfo['remember_key'] = $user->remember_key;
		}
		return $userinfo;
	}

	/**
	 * Create a xen User from the session
	 *
	 * @return multitype:|multitype:string NULL mixed
	 */
	public function xenUserFromSession()
	{
		$userinfo = array();
		$query = "SELECT * from xf_session WHERE session_id ='{$this->session_id}'";
		$this->db->setQuery($query);
		$xensession = $this->db->loadObject();
		if (!empty($xensession->session_data))
		{

			$authinfo = unserialize($xensession->session_data);

			// Get userid from session data
			$userinfo['userid'] = $authinfo['user_id'];
			$query = "SELECT u.email, u.username, a.remember_key
			FROM xf_user u
			LEFT JOIN xf_user_authenticate a ON u.user_id = a.user_id
			WHERE u.user_id ='{$userinfo['userid']}'";
			$this->db->setQuery($query);
			$xenuser = $this->db->loadObject();
			$userinfo['remember_key'] = $xenuser->remember_key;
		}
		else
		{
			// We have no session in the database
			if (!empty($this->cookie_persist))
			{
				// OK, we have a persistant cookie
				// Lets get the info from that.
				list($userinfo['userid'], $userinfo['cookie_key']) = explode(',', $this->cookie_persist);

				if (!empty($userinfo['userid']))
				{
					// We have a user ID, so lets get the user details
					$query = "SELECT u.email, u.username, a.remember_key
					FROM xf_user u
					LEFT JOIN xf_user_authenticate a ON u.user_id = a.user_id
					WHERE u.user_id ='{$userinfo['userid']}'";
					$this->db->setQuery($query);
					$xenuser = $this->db->loadObject();

					$verifyKey = sha1($this->global_salt . $xenuser->remember_key);
					if ($userinfo['cookie_key'] != $verifyKey)
					{
						// Cookie key is invalid, delete session
						$this->deleteSession();
						return array();
					}
					$userinfo['remember_key'] = $verifyKey;
				}

			}
		}

		$userinfo['email'] = $xenuser->email;
		$userinfo['username'] = $xenuser->username;

		return $userinfo;
	}

	/**
	 * Change the xenforo user's group
	 *
	 * @param   int     $userid     User id
	 * @param   int     $usergroup  User group
	 * @param   string  $error      Error
	 * @param   array   &$status    Status (with error key)
	 * @param   bool    $activate   Should the xenforo be active (false) or need activating (true)
	 * @param   bool    $primary    Is this the primary xenforo user group
	 *
	 * @return  void
	 */
	public function changeUserGroup($userid, $usergroup, $error, &$status, $activate, $primary = 1)
	{
		$primary = (int) $primary;

		/**
		 * HACK TILL WE GET ADMIN INTEFACE DONE FOR USER GROUP MAP
		 */

		// Key J User group => value Xen user group
		$map = array();

		// Supporter
		$map[9] = 46;

		// Standard
		$map[10] = 41;

		// Pro
		$map[11] = 44;

		// Set the user group from the J user group to the xen user group
		$usergroup = $map[$usergroup];

		$query = "UPDATE xf_user
		SET user_group_id = {$usergroup},
		display_style_group_id = {$usergroup}, ";

		if ($activate)
		{
			$query .= "user_state = " . $this->db->quote('email_confirm') . " ";
		}
		else
		{
			$query .= "user_state = " . $this->db->quote('valid') . " ";
		}
		$query .= " WHERE user_id = {$userid}";

		$this->db->setQuery($query);
		if (!$this->db->Query())
		{
			$status['error'][] = $error . $this->db->stderr();
			return;
		}
		// Does an entry already exist
		$query = "SELECT COUNT(*) FROM xf_user_group_relation WHERE user_id = " . (int) $userid
		. " AND is_primary = $primary";
		$this->db->setQuery($query);
		$found = $this->db->loadResult();

		if ($found > 0)
		{
			$query = "UPDATE xf_user_group_relation
			SET user_group_id = {$usergroup}
			WHERE user_id = {$userid}
			AND   is_primary = $primary";
		}
		else
		{
			$query = "INSERT INTO xf_user_group_relation
			(user_group_id, user_id, is_primary) VALUES ({$usergroup}, $userid, $primary)";
		}
		$this->db->setQuery($query);
		if (!$this->db->Query())
		{
			$status['error'][] = $error . $this->db->stderr();
			return;
		}
		return;
	}

	/**
	 * Get XenForo User Object
	 *
	 * @return  void
	 */
	public function getUserObject()
	{
	}

	/**
	 * Creat XenForo User
	 *
	 * @param   object  $userinfo  User info
	 *
	 * @return void|string
	 */
	public function createUser($userinfo)
	{
		$datestamp = time();
		$ip = ip2long($this->ipaddress);
		$params = JFusionFactory::getParams($this->getJname());

		// Update $userinfo with group premission info
		$this->mapUsergroup($params->get('usergroup'), $userinfo);

		/* Create basic user details */
		$user = new stdClass;
		$user->username = $this->filterUsername($userinfo->username);
		$user->email = $userinfo->email;
		$user->gender = '';
		$user->custom_title = '';

		// 1 is English, the default
		$user->language_id = 1;
		$user->style_id = 0;
		$user->timezone = 'Europe/London';
		$user->visible = 1;
		$user->user_group_id = $userinfo->user_group_id;
		$user->secondary_group_ids = '';

		// Research
		$user->display_style_group_id = $userinfo->display_style_group_id;

		// From xf_permission_combination, needs research
		$user->permission_combination_id = $userinfo->permission_combination_id;
		$user->message_count = 0;
		$user->conversations_unread = 0;
		$user->trophy_points = 0;
		$user->alerts_unread = 0;
		$user->avatar_date = 0;
		$user->avatar_width = 0;
		$user->avatar_height = 0;
		$user->gravatar = '';
		$user->user_state = 'valid';
		$user->is_moderator = 0;
		$user->is_admin = 0;
		$user->is_banned = intval($userinfo->block);
		$user->like_count = 0;

		// Insert user record
		if (!$this->db->insertObject('xf_user', $user, 'user_id'))
		{
			// Return the error
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $this->db->stderr();
			return;
		}
		else
		{
			// Return the good news
			$status['debug'][] = JText::_('USER_CREATION');
			$userinfo->user_id = $user->user_id;

			// TODO $status['userinfo'] = $this->getUser($userinfo);
		}

		// Create authentication record

		if (!$this->db->insertObject('xf_user_authenticate', $this->createAuthObject($userinfo)))
		{
			// Return the error
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $this->db->stderr();
			return;
		}

		// Insert primary user group
		$primaryGroup = new stdClass;
		$primaryGroup->user_id = $user->user_id;
		$primaryGroup->user_group_id = $userinfo->user_group_id;
		$primaryGroup->is_primary = 1;

		if (!$this->db->insertObject('xf_user_group_relation', $primaryGroup))
		{
			// Return the error
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $this->db->stderr();
			return;
		}

		// TODO insert additional user groups, if any
		$ipRecord = new stdClass;
		$ipRecord->user_id = $user->user_id;
		$ipRecord->content_type = 'user';
		$ipRecord->content_id = 1;
		$ipRecord->action = 'login';
		$ipRecord->ip = $ip;
		$ipRecord->log_date = $datestamp;

		if (!$this->db->insertObject('xf_ip', $ipRecord))
		{
			// Return the error
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $this->db->stderr();
			return;
		}

		// Get default user options
		$query = "SELECT option_value, default_value
				FROM xf_option
				WHERE option_id = 'registrationDefaults'";
		$this->db->setQuery($query);
		$userDefaultOption = $this->db->loadObject();
		$options = (object) unserialize(empty($userDefaultOption->option_value) ? $userDefaultOption->default_value : $userDefaultOption->option_value);
		$userOptions = new stdClass;
		$userOptions->user_id = $user->user_id;
		$userOptions->show_dob_year = $options->show_dob_year;
		$userOptions->show_dob_date = $options->show_dob_date;
		$userOptions->content_show_signature = $options->content_show_signature;
		$userOptions->receive_admin_email = $options->receive_admin_email;
		$userOptions->email_on_conversation = $options->email_on_conversation;
		$userOptions->default_watch_state = $options->default_watch_state;
		$userOptions->is_discouraged = 0;
		$userOptions->alert_optout = 0;
		$userOptions->enable_rte = 1;

		if (!$this->db->insertObject('xf_user_option', $userOptions))
		{
			// Return the error
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $this->db->stderr();
			return;
		}

		$privacyRecord = new stdClass;
		$privacyRecord->user_id = $user->user_id;
		$privacyRecord->allow_view_profile = 'everyone';
		$privacyRecord->allow_post_profile = 'everyone';
		$privacyRecord->allow_send_personal_conversation = 'everyone';
		$privacyRecord->allow_view_identities = 'everyone';
		$privacyRecord->allow_receive_news_feed = 'everyone';

		if (!$this->db->insertObject('xf_user_privacy', $privacyRecord))
		{
			// Return the error
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $this->db->stderr();
			return;
		}

		$this->createuserProfile($user, $status);

		$confirmation_key = $this->generateRandomString(16);

		$confirmRecord = new stdClass;
		$confirmRecord->user_id = $user->user_id;
		$confirmRecord->confirmation_type = 'email';
		$confirmRecord->confirmation_key = $this->generateRandomString(16);
		$confirmRecord->confirmation_date = $datestamp;

		if (!$this->db->insertObject('xf_user_confirmation', $confirmRecord))
		{
			// Return the error
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $this->db->stderr();
			return;
		}
		return $status;
	}

	/**
	 * Create a user profile. A xenforo user MUST have a profile for him to show up in
	 * xenforo's admin
	 *
	 * @param   object  $user     User
	 * @param   array   &$status  Status
	 *
	 * @return  void
	 */
	public function createUserProfile($user, $existinguser, &$status)
	{
		$profileRecord = new stdClass;
		$profileRecord->user_id = $existinguser->userid;
		$profileRecord->dob_day = 0;
		$profileRecord->dob_month = 0;
		$profileRecord->dob_year = 0;
		$profileRecord->status = '';
		$profileRecord->status_date = 0;
		$profileRecord->status_profile_post_id = 0;
		$profileRecord->signature = '';
		$profileRecord->homepage = '';
		$profileRecord->location = '';
		$profileRecord->occupation = '';
		$profileRecord->following = '';

		// Not found in xenforo 1.1.4 $profileRecord->identities = '';
		$profileRecord->csrf_token = $this->generateRandomString(40);
		$profileRecord->avatar_crop_x = 0;
		$profileRecord->avatar_crop_y = 0;
		$profileRecord->about = '';
		$profileRecord->facebook_auth_id = 0;

		$this->db->setQuery('SELECT COUNT(*) FROM xf_user_profile WHERE user_id = ' . (int) $existinguser->userid);
		$found = (int) $this->db->loadResult();
		if ($found === 0)
		{
			if (!$this->db->insertObject('xf_user_profile', $profileRecord))
			{
				// Return the error
				$status['error'][] = JText::_('USER_CREATION_ERROR') . $this->db->stderr();
			}
			else
			{
				$status['debug'][] = 'Xenforo: create user profile for userid: ' . $existinguser->userid;
			}
		}
	}

	/**
	 * Add details to $userinfo about group settings
	 *
	 * @param   int     $usergroup  User group
	 * @param   object  &$userinfo  User info
	 *
	 * @return  void
	 */
	private function mapUsergroup($usergroup, &$userinfo)
	{
		/*
		 * TODO advanced mode mapping
		* work out display_style_group_id
		* work out permission_combination_id
		* work out user_group_id
		*/

		// Starting point only
		$userinfo->user_group_id = $usergroup;
		$query = "SELECT permission_combination_id
		FROM xf_permission_combination
		WHERE user_group_list = {$usergroup}";
		$this->db->setQuery($query);
		$groupInfo = $this->db->loadObject();
		$userinfo->permission_combination_id = $groupInfo->permission_combination_id;
		$userinfo->display_style_group_id = $usergroup;
	}

	/**
	 * Create the user authorisation record for XenForum
	 *
	 * @param   object  $userinfo  User info
	 *
	 * @return stdClass
	 */
	private function createAuthObject($userinfo)
	{
		$authRecord = new stdClass;
		$authRecord->user_id = $userinfo->user_id;
		$authRecord->remember_key = $this->generateRandomString(40);

		if (!empty($userinfo->password_clear))
		{
			require_once JPATH_ADMINISTRATOR . '/components/com_jfusion/models/model.factory.php';
			$JFusionAuth = JFusionFactory::getAuth($this->getJname());

			// We have the original password, so use the xenforo encoding
			$authRecord->scheme_class = 'XenForo_Authentication_Core';
			$userinfo->hashFunc = 'sha256';

			/* {hash, salt, hashFunc) */
			$data['hash'] = $JFusionAuth->generateEncryptedPassword($userinfo);
			$data['hashFunc'] = $userinfo->hashFunc;
		}
		else
		{
			/* {hash, salt) */
			// No original password, so use the Joomla class for authentication
			$authRecord->scheme_class = 'JoomlaBridge_Authentication_Joomla';
			$data['hash'] = $userinfo->password;
			$data['salt'] = $userinfo->password_salt;
		}
		$authRecord->data = serialize($data);

		return $authRecord;
	}
}
