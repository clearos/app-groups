<?php

/**
 * Groups controller.
 *
 * @category   Apps
 * @package    Groups
 * @subpackage Controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/groups/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

use \clearos\apps\accounts\Accounts_Engine as Accounts_Engine;
use \clearos\apps\groups\Group_Engine as Group;

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Groups controller.
 *
 * The app policy system is group driven.  This controller is used by various
 * apps to manage these group policies.  For security reasons, the group list
 * is set explicitly in the constructor.  In other words, the "PPTP Server"
 * will use this controller to manage the "pptpd_server_plugin" group
 * (and *only* this app plugin group).
 *
 * @category   Apps
 * @package    Groups
 * @subpackage Controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/groups/
 */

class Groups extends ClearOS_Controller
{
    protected $app_name = NULL;
    protected $group_list = NULL;
    protected $show_unhappy = FALSE;

    /**
     * Group membership constructor.
     *
     * The group page itself does not specify parameters with this constructor.
     * All others do so (e.g. PPTP Server policy, Web Proxy policy).
     * In other words, the first two parameters are really mandatory.
     *
     * @param string  $app_name     app that manages the group
     * @param string  $group_list   group name
     * @param boolean $show_unhappy show widget when unhappy
     *
     * @return view
     */

    function __construct($app_name = NULL, $group_list = NULL, $show_unhappy = FALSE)
    {
        if (is_null($app_name)) {
            $this->show_unhappy = TRUE;
        } else {
            $this->app_name = $app_name;
            $this->group_list = $group_list;
            $this->show_unhappy = $show_unhappy;
        }
    }

    /**
     * Groups server overview.
     *
     * @return view
     */

    function index()
    {
        // Show account status widget if we're not in a happy state
        //---------------------------------------------------------

        $this->load->module('accounts/status');

        if ($this->status->unhappy()) {
            if ($this->show_unhappy)
                $this->status->widget('groups');
            return;
        }

        // Show cache widget if using remote accounts (e.g. AD)
        //-----------------------------------------------------

        $this->load->module('accounts/cache');

        if ($this->cache->needs_reset()) {
            $app_name = empty($this->app_name) ? 'groups' :  $this->app_name;
            $this->cache->widget($app_name);
            return;
        }

        if (empty($this->app_name))
            $this->index_all();
        else
            $this->index_policy();

    }

    function index_all()
    {
        // Load libraries
        //---------------

        $this->lang->load('groups');
        $this->load->factory('groups/Group_Manager_Factory');
        $this->load->factory('accounts/Accounts_Factory');
        $this->load->library('accounts/Accounts_Configuration');

        // Load view data
        //---------------

        try {
            $data['groups'] = $this->group_manager->get_details(Group::FILTER_ALL);

            if ($this->accounts->get_capability() === Accounts_Engine::CAPABILITY_READ_WRITE)
                $data['mode'] = 'edit';
            else
                $data['mode'] = 'view';

            if ($this->accounts_configuration->get_driver() == 'active_directory')
                $data['cache_action'] = TRUE;
            else
                $data['cache_action'] = FALSE;
        } catch (Engine_Exception $e) {
            $this->page->view_exception($e);
            return;
        }
 
        // Load views
        //-----------

        $options['javascript'] = array(clearos_app_htdocs('accounts') . '/cache.js.php');

        $this->page->view_form('groups/summary', $data, lang('groups_group_manager'), $options);
    }

    function index_policy()
    {
        // Load libraries
        //---------------

        $this->lang->load('groups');
        $this->load->factory('groups/Group_Manager_Factory');
        $this->load->factory('accounts/Accounts_Factory');

        // Load view data
        //---------------

        try {
            $data['basename'] = $this->app_name;
            $data['groups'] = array();

            $all_groups = $this->group_manager->get_details(Group::FILTER_PLUGIN);

            foreach ($this->group_list as $group) {
                if (array_key_exists($group, $all_groups))
                    $data['groups'][] = $all_groups[$group];
            }

            if ($this->accounts->get_capability() === Accounts_Engine::CAPABILITY_READ_WRITE)
                $data['mode'] = 'edit';
            else
                $data['mode'] = 'view';

        } catch (Engine_Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load views
        //-----------

        $options['javascript'] = array(clearos_app_htdocs('accounts') . '/cache.js.php');

        $this->page->view_form('groups/policies', $data, lang('groups_group_manager'), $options);
    }

    /**
     * Group add view.
     *
     * @param string $group_name groupname
     *
     * @return view
     */

    function add($group_name)
    {
        if (!isset($group_name) && $this->input->post('group_name'))
            $group_name = $this->input->post('group_name');

        $this->_handle_item('add', $group_name);
    }

    /**
     * Group delete view.
     *
     * @param string $group_name group name
     *
     * @return view
     */

    function delete($group_name)
    {
        $confirm_uri = '/app/groups/destroy/' . $group_name;
        $cancel_uri = '/app/groups';
        $items = array($group_name);

        $this->page->view_confirm_delete($confirm_uri, $cancel_uri, $items);
    }

    /**
     * Destroys group.
     *
     * @param string $group_name group name
     *
     * @return view
     */

    function destroy($group_name)
    {
        // Load libraries
        //---------------

        $this->load->factory('groups/Group_Factory', $group_name);

        // Handle form submit
        //-------------------

        try {
            $this->group->delete();
            $this->page->set_status_deleted();
            redirect('/groups');
        } catch (Engine_Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Group edit view.
     *
     * @param string $group_name group name
     *
     * @return view
     */

    function edit($group_name)
    {
        $this->_handle_item('edit', $group_name);
    }

    /**
     * Group edit members view.
     *
     * @param string $group_name group name
     *
     * @return view
     */

    function edit_members($group_name)
    {
        $this->_handle_members('edit', $group_name);
    }

    /**
     * User view.
     *
     * @param string $group_name group_name
     *
     * @return view
     */

    function view($group_name)
    {
        $this->_handle_item('view', $group_name);
    }

    /**
     * Group members view.
     *
     * @param string $group_name group name
     *
     * @return view
     */

    function view_members($group_name)
    {
        $this->_handle_members('view', $group_name);
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Group common view/edit members form handler.
     *
     * @param string $form_type  form type (add, edit or view)
     * @param string $group_name group_name
     *
     * @return view
     */

    function _handle_members($form_type, $group_name)
    {
        // Load libraries
        //---------------

        $this->lang->load('groups');
        $this->load->factory('users/User_Manager_Factory');
        $this->load->factory('groups/Group_Factory', $group_name);

        // Check group policy
        //-------------------

        if (! empty($this->group_list) && (!in_array($group_name, $this->group_list))) {
            throw new Exception('not allowed');
            $this->page->view_exception($e);
            return;
        }

        // Handle form submit
        //-------------------

        if ($this->input->post('submit')) {
            try {
                $users = array();
                foreach ($this->input->post('users') as $user => $state)
                    $users[] = $user;
                
                $this->group->set_members($users);

                $this->page->set_status_updated();

                if (empty($this->app_name))
                    redirect('/groups');
                else
                    redirect($this->app_name);

            } catch (Engine_Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        // Load the view data 
        //------------------- 

        try {
            $data['mode'] = $form_type;
            $data['basename'] = empty($this->app_name) ? '' : $this->app_name;
            $data['group_info'] = $this->group->get_info();
            $data['users'] = $this->user_manager->get_details();
        } catch (Engine_Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load the views
        //---------------

        $this->page->view_form('groups/members', $data, lang('groups_members'));
    }

    /**
     * Group common add/edit form handler.
     *
     * @param string $form_type  form type (add, edit or view)
     * @param string $group_name group_name
     *
     * @return view
     */

    function _handle_item($form_type, $group_name)
    {
        // Load libraries
        //---------------

        $this->lang->load('groups');
        $this->load->factory('groups/Group_Factory', $group_name);
        $this->load->factory('accounts/Accounts_Factory');

        // Check group policy
        //-------------------

        if (! empty($this->group_list)) {
            throw new Exception('not allowed');
            $this->page->view_exception($e);
            return;
        }

        // Grab info map for validation
        //-----------------------------

        try {
            $info_map = $this->group->get_info_map();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Set validation rules
        //---------------------

        $this->load->library('form_validation');

        // TODO: need to make this driver friendly instead of hard-coding openldap_directory
        $this->form_validation->set_policy('description', 'openldap_directory/Group_Driver', 'validate_description', TRUE);

        if ($form_type === 'add')
            $this->form_validation->set_policy('group_name', 'openldap_directory/Group_Driver', 'validate_group_name', TRUE);


        // Validate extensions
        //--------------------

        if (! empty($info_map['extensions'])) {
            foreach ($info_map['extensions'] as $extension => $parameters) {
                foreach ($parameters as $key => $details) {
                    $required = (isset($details['required'])) ? $details['required'] : FALSE;
                    $full_key = 'group_info[extensions][' . $extension . '][' . $key . ']';

                    $this->form_validation->set_policy($full_key, $details['validator_class'], $details['validator'], $required);
                }
            }
        }

        $form_ok = $this->form_validation->run();

        // Handle form submit
        //-------------------

        if ($this->input->post('submit') && ($form_ok === TRUE)) {
            try {
                $group_info = $this->input->post('group_info');
                $group_info['core']['description'] = $this->input->post('description');

                if ($form_type === 'add')
                    $this->group->add($group_info);
                else if ($form_type === 'edit')
                    $this->group->update($group_info);

                $this->page->set_status_updated();
                redirect('/groups');
            } catch (Engine_Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        // Load the view data 
        //------------------- 

        try {
            $data['form_type'] = $form_type;
            $data['info_map'] = $info_map;
            $data['extensions'] = $this->accounts->get_extensions();

            if ($form_type === 'add')
                $data['group_info'] = $this->group->get_info_defaults();
            else
                $data['group_info'] = $this->group->get_info();
        } catch (Engine_Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load the views
        //---------------

        $this->page->view_form('groups/item', $data, lang('groups_group_manager'));
    }
}
