<?php

/**
 * Kolab Dokuwiki Plugin
 *
 * @author Aleksander 'A.L.E.C' Machniak <machniak@kolabsys.com>
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 * @licence GNU AGPL
 *
 * Configuration (see config.inc.php)
 *
 *
 * Modified OwnCloud Plugin
 *
 * For description visit:
 * http://blog.sleeplessbeastie.eu/2013/07/01/kolab-how-to-integrate-dokuwiki/
 */


class dokuwiki extends rcube_plugin
{
    // all task excluding 'login'
   public $task = '?(?!login).*';
    // skip frames
    public $noframe = true;

    function init()
    {
        // requires kolab_auth plugin
        if (empty($_SESSION['kolab_uid'])) {
            $_SESSION['kolab_uid'] = $_SESSION['username'];
        }

        $rcmail = rcube::get_instance();

        $this->add_texts('localization/', false);

        // register task
        $this->register_task('dokuwiki');

        // register actions
        $this->register_action('index', array($this, 'action'));
        $this->add_hook('session_destroy', array($this, 'logout'));

        // handler for sso requests sent by the dokuwiki kolab_auth app
        if ($rcmail->action == 'dokuwikisso' && !empty($_POST['token'])) {
            $this->add_hook('startup', array($this, 'sso_request'));
        }

        // add taskbar button
        $this->add_button(array(
            'command'    => 'dokuwiki',
            'class'      => 'button-dokuwiki',
            'classsel'   => 'button-dokuwiki button-selected',
            'innerclass' => 'button-inner',
            'label'      => 'dokuwiki.dokuwiki',
            ), 'taskbar');

        // add style for taskbar button (must be here) and Help UI
        $this->include_stylesheet($this->local_skin_path()."/dokuwiki.css");
    }

    function action()
    {
        $rcmail = rcube::get_instance();

        $rcmail->output->add_handlers(array('dokuwikiframe' => array($this, 'frame')));
        $rcmail->output->set_pagetitle($this->gettext('dokuwiki'));
        $rcmail->output->send('dokuwiki.dokuwiki');
    }

    function frame()
    {
        $rcmail = rcube::get_instance();
        $this->load_config();

        // generate SSO auth token
        if (empty($_SESSION['dokuwikiauth']))
            $_SESSION['dokuwikiauth'] = md5('dokuwikisso' . $_SESSION['user_id'] . microtime() . $rcmail->config->get('des_key'));

        $src  = $rcmail->config->get('dokuwiki_url');
        $src .= '?kolab_auth=' . strrev(rtrim(base64_encode(http_build_query(array(
            'session' => session_id(),
            'cname'   => session_name(),
            'token'   => $_SESSION['dokuwikiauth'],
        ))), '='));

        return html::tag('iframe', array('id' => 'dokuwikiframe', 'src' => $src,
            'width' => "100%", 'height' => "100%", 'frameborder' => 0));
    }

    function logout()
    {
        $rcmail = rcube::get_instance();
        $this->load_config();

        // send logout request to dokuwiki
        $logout_url = $rcmail->config->get('dokuwiki_url') . '?do=logout';
        $rcmail->output->add_script("new Image().src = '$logout_url';", 'foot');
    }

    function sso_request()
    {
        $response = array();
        $sign_valid = false;

        $rcmail = rcube::get_instance();
        $this->load_config();

        // check signature
        if ($hmac = $_POST['hmac']) {
            unset($_POST['hmac']);
            $postdata = http_build_query($_POST, '', '&');
            $sign_valid = ($hmac == hash_hmac('sha256', $postdata, $rcmail->config->get('dokuwiki_secret', '<undefined-secret>')));
        }

        // if Dokuwiki sent a valid auth request, return plain username and password
        if ($sign_valid && !empty($_POST['token']) && $_POST['token'] == $_SESSION['dokuwikiauth']) {
            $user = $_SESSION['kolab_uid']; // requires kolab_auth plugin
            $pass = $rcmail->decrypt($_SESSION['password']);
            $response = array('user' => $user, 'pass' => $pass);
        }

        echo json_encode($response);
        exit;
    }

}
