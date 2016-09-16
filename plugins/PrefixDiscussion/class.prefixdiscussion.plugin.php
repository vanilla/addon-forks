<?php

$PluginInfo['PrefixDiscussion'] = array(
    'Name' => 'Prefix Discussion',
    'Description' => 'Allows prefixing discussion titles with a configurable set of terms.',
    'Version' => '1.2',
    'RequiredApplications' => array('Vanilla' => '2.2'),
    'MobileFriendly' => true,
    'HasLocale' => true,
    'RegisterPermissions' => array(
        'Vanilla.PrefixDiscussion.Add',
        'Vanilla.PrefixDiscussion.View',
        'Vanilla.PrefixDiscussion.Manage'
    ),
    'Author' => 'Robin Jurinka',
    'AuthorUrl' => 'https://vanillaforums.org/profile/44046/R_J',
    'SettingsUrl' => '/dashboard/settings/PrefixDiscussion',
    'SettingsPermission' => 'Vanilla.PrefixDiscussion.Manage',
    'License' => 'MIT'
);

/**
 * PrefixDiscussion allows users to add prefixes to discussions.
 *
 * Permissions must be set properly in order to
 * a) allow adding,
 * b) allow viewing and
 * c) allow managing
 * prefixes.
 *
 * @package PrefixDiscussion
 * @author Robin Jurinka
 * @license MIT
 */
class PrefixDiscussionPlugin extends Gdn_Plugin {
    /**
     * Get the prefixes' separator
     *
     * @return string Prefix separator
     * @package PrefixDiscussion
     * @since 1.0
     */
    public static function getPrefixesSeparator() {
        return c('PrefixDiscussion.ListSeparator', ';');
    }

    /**
     * Get the list of prefixes
     *
     * @return array List of prefixes
     * @package PrefixDiscussion
     * @since 0.2
     */
    public static function getPrefixes() {
        static $cachedPrefixes = null;

        if ($cachedPrefixes === null) {
            $cachedPrefixes = array();

            // Get prefixes from config.
            $prefixes = explode(
                self::getPrefixesSeparator(),
                c('PrefixDiscussion.Prefixes', 'Question'.self::getPrefixesSeparator().'Solved')
            );

            // Trim and remove empty prefixes.
            $prefixes = array_filter(
                array_map('trim', $prefixes)
            );

            if (count($prefixes)) {
                $cachedPrefixes = array_combine($prefixes, $prefixes);
            }
        }

        return $cachedPrefixes;
    }

    /**
     * Setup is called when plugin is enabled and prepares config and db.
     *
     * @package PrefixDiscussion
     * @since 0.1
     */
    public function setup() {
        // Init some config settings.
        touchConfig(
            array(
                'PrefixDiscussion.ListSeparator' => ';',
                'PrefixDiscussion.Prefixes' => 'Question;Solved'
            )
        );
        // Change db structure.
        $this->structure();
    }

    /**
     * Structure is called by setup() and adds column to discussion table.
     *
     * @package PrefixDiscussion
     * @since 0.1
     */
    public function structure() {
        Gdn::database()->structure()
            ->table('Discussion')
            ->column('Prefix', 'varchar(64)', true)
            ->set();

        /*
         * Before version 1.1, when a discussion used an empty prefix, an empty string was
         * inserted in the DB. Records that were created before the plugin was installed
         * had NULL as value. It is generally not a good idea to mix empty strings and NULLs values.
         */
        $fixDone = c('PrefixDiscussion.PrefixMixingFixDone', false); // If we update from an older version
        if (!$fixDone) {
            Gdn::sql()->update('Discussion', array('Prefix' => null), array('Prefix' => ''));
            saveToConfig('PrefixDiscussion.PrefixMixingFixDone', true);
        }
    }

    /**
     * Barebone config screen.
     *
     * @param SettingsController $sender Sending controller instance.
     * @package PrefixDiscussion
     * @since 0.1
     */
    public function settingsController_prefixDiscussion_create($sender) {
        $sender->permission('Vanilla.PrefixDiscussion.Manage');
        $sender->setData('Title', t('Prefix Discussion Settings'));
        $sender->addSideMenu('dashboard/settings/plugins');

        $configurationModule = new ConfigurationModule($sender);
        $configurationModule->initialize(array(
            'PrefixDiscussion.Prefixes',
            'PrefixDiscussion.ListSeparator'
        ));
        $configurationModule->renderAll();
    }

    /**
     * Render input box.
     *
     * @param PostController $sender Sending controller instance.
     * @package PrefixDiscussion
     * @since 0.1
     */
    public function postController_beforeBodyInput_handler($sender) {
        // Only show dropdown if permission is set.
        if (!checkPermission('Vanilla.PrefixDiscussion.Add')) {
            return;
        }

        // Add stylesheet so that prefixes can be styled.
        $sender->addCssFile('prefixdiscussion.css', 'plugins/prefixDiscussion');

        // Render output.
        $noPrefix = array('' => t('PrefixDiscussion.None', '-'));
        echo '<div class="P PrefixDiscussion">';
        echo $sender->Form->label('Discussion Prefix', 'Prefix');
        echo $sender->Form->dropDown('Prefix', $noPrefix + self::getPrefixes());
        echo '</div>';
    }

    /**
     * Add prefix to discussion title.
     *
     * @param DiscussionController $sender Sending controller instance.
     * @package PrefixDiscussion
     * @since 0.1
     */
    public function discussionController_beforeDiscussionRender_handler($sender) {
        if (!checkPermission('Vanilla.PrefixDiscussion.View')) {
            return;
        }
        $prefix = $sender->Discussion->Prefix;
        if ($prefix == '') {
            return;
        }
        $sender->addCssFile('prefixdiscussion.css', 'plugins/prefixDiscussion');
        $sender->Discussion->Name = wrap(
            $prefix,
            'span',
            array('class' => 'PrefixDiscussion Sp'.str_replace(' ', '_', $prefix))
        ).$sender->Discussion->Name;
    }

    /**
     * Add prefix to discussions lists.
     *
     * Does not work for table view since there is no appropriate event
     * in Vanilla 2.1.
     *
     * @param object $sender Sending controller instance.
     * @param array $args Event arguments.
     * @package PrefixDiscussion
     * @since 0.1
     */
    public function base_beforeDiscussionName_handler($sender, $args) {
        if (!checkPermission('Vanilla.PrefixDiscussion.View')) {
            return;
        }
        $prefix = $args['Discussion']->Prefix;
        if ($prefix == '') {
            return;
        }
        $args['Discussion']->Name = wrap(
            $prefix,
            'span',
            array('class' => 'PrefixDiscussion Sp'.str_replace(' ', '_', $prefix))
        ).$args['Discussion']->Name;
    }

    /**
     * Add css to discussions list if needed.
     *
     * @param DiscussionsController $sender Sending controller instance.
     * @package PrefixDiscussion
     * @since 0.1
     */
    public function discussionsController_render_before($sender) {
        $sender->addCssFile('prefixdiscussion.css', 'plugins/prefixDiscussion');
    }

    /**
     * Add css to categories list if needed.
     *
     * @param CategoriesController $sender Sending controller instance.
     * @package PrefixDiscussion
     * @since 0.1
     */
    public function categoriesController_render_before($sender) {
        $sender->addCssFile('prefixdiscussion.css', 'plugins/prefixDiscussion');
    }

    /**
     * Prevent from mixing NULLs and empty strings in the DB
     *
     * @param DiscussionModel $sender Sending controller instance.
     * @param array $args Event arguments.
     * @package PrefixDiscussion
     * @since 1.1
     */
    public function discussionModel_beforeSaveDiscussion_handler($sender, $args) {
        if (!in_array($args['FormPostValues']['Prefix'], self::getPrefixes())) {
            $args['FormPostValues']['Prefix'] = null;
        }
    }
}
