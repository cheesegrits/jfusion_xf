<?php
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion auth Class for xenforo
 *
 * @category    JFusion
 * @package     JFusionPlugins
 * @subpackage  xenforo
 * @author      Martin Cooper <mac@martinc.me.uk>
 * @copyright   2011 Martin Cooper. All rights reserved.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link        http://www.martinc.me.uk
*/
class JFusionAuth_xenforo extends JFusionAuth
{
	function generateEncryptedPassword(&$userinfo)
	{
		switch ($userinfo->scheme_class)
		{
			case 'XenForo_Authentication_Core':
				switch ($userinfo->hashFunc)
				{
					case 'sha256':
						$passhash = hash('sha256', hash('sha256', $userinfo->password_clear).$userinfo->password_salt);
						break;
					default:
						$passhash = sha1(sha1($userinfo->password_clear).$userinfo->password_salt);
						break;
				}
				break;
			case 'XenForo_Authentication_vBulletin':
				$passhash = md5(md5($userinfo->password_clear) . $userinfo->password_salt);
				break;
		}
		return $passhash;
	}

	function getJname()
	{
		return 'xenforo';
	}
}
