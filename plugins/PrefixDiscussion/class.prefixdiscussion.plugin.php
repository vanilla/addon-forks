<?php

$PluginInfo['PrefixDiscussion'] = [
    'Name' => 'Prefix Discussion',
    'Description' => 'Allows prefixing discussion titles with a configurable set of terms.',
    'Version' => '1.4.0',
    'RequiredApplications' => ['Vanilla' => '2.3'],
    'MobileFriendly' => true,
    'HasLocale' => true,
    'RegisterPermissions' => [
        'Vanilla.PrefixDiscussion.Add',
        'Vanilla.PrefixDiscussion.View',
        'Vanilla.PrefixDiscussion.Manage'
    ],
    'Author' => 'Robin Jurinka',
    'AuthorUrl' => 'https://vanillaforums.org/profile/r_j',
    'SettingsUrl' => '/dashboard/settings/prefixdiscussion',
    'SettingsPermission' => 'Vanilla.PrefixDiscussion.Manage',
    'License' => 'MIT'
];

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
     * @package PrefixDiscussion
     * @since 1.0
     * @return string Prefix separator
     */
    public static function getPrefixesSeparator() {
        return c('PrefixDiscussion.ListSeparator', ';');
    }

    /**
     * Get the list of prefixes
     *
     * @package PrefixDiscussion
     * @since 0.2
     * @return array List of prefixes
     */
    public static function getPrefixes() {
        static $cachedPrefixes = null;

        if ($cachedPrefixes === null) {
            $cachedPrefixes = [];

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
     * @return  void.
     */
    public function setup() {
        // Init some config settings.
        touchConfig([
            'PrefixDiscussion.ListSeparator' => ';',
            'PrefixDiscussion.Prefixes' => 'Question;Solved'
        ]);
        // Change db structure.
        $this->structure();
    }

    /**
     * Structure is called by setup() and adds column to discussion table.
     *
     * @package PrefixDiscussion
     * @since 0.1
     * @return  void.
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
        if (c('PrefixDiscussion.PrefixMixingFixDone', false) != true) {
            // If we update from an older version
            $sql = clone Gdn::sql();
            $sql->reset();
            $sql->update('Discussion', ['Prefix' => null], ['Prefix' => '']);
            saveToConfig('PrefixDiscussion.PrefixMixingFixDone', true);
        }
    }

    /**
     * Barebone config screen.
     *
     * @param SettingsController $sender Sending controller instance.
     *
     * @package PrefixDiscussion
     * @since 0.1
     * @return void.
     */
    public function settingsController_prefixDiscussion_create($sender) {
        $sender->permission('Vanilla.PrefixDiscussion.Manage');
        $sender->setData('Title', t('Prefix Discussion Settings'));
        $sender->addSideMenu('dashboard/settings/plugins');

        $configurationModule = new ConfigurationModule($sender);
        $configurationModule->initialize([
            'PrefixDiscussion.Prefixes',
            'PrefixDiscussion.ListSeparator'
        ]);
        $configurationModule->renderAll();
    }

    /**
     * Render input box.
     *
     * @param PostController $sender Sending controller instance.
     *
     * @package PrefixDiscussion
     * @since 0.1
     * @return  void.
     */
    public function postController_beforeBodyInput_handler($sender) {
        // Only show dropdown if permission is set.
        if (!checkPermission('Vanilla.PrefixDiscussion.Add')) {
            return;
        }

        // Add stylesheet so that prefixes can be styled.
        $sender->addCssFile('prefixdiscussion.css', 'plugins/prefixDiscussion');

        // Render output.
        $noPrefix = ['' => t('PrefixDiscussion.None', '-')];
        echo '<div class="P PrefixDiscussion">';
        echo $sender->Form->label('Discussion Prefix', 'Prefix');
        echo $sender->Form->dropDown('Prefix', $noPrefix + self::getPrefixes());
        echo '</div>';
    }

    /**
     * Add prefix to discussion title.
     *
     * @param DiscussionController $sender Sending controller instance.
     *
     * @package PrefixDiscussion
     * @since 0.1
     * @return  void.
     */
    public function discussionController_beforeDiscussionRender_handler($sender) {
        if (!checkPermission('Vanilla.PrefixDiscussion.View')) {
            return;
        }
        $discussion = $sender->data('Discussion');
        $prefix = val('Prefix', $discussion, '');
        if ($prefix == '') {
            return;
        }
        $sender->addCssFile('prefixdiscussion.css', 'plugins/prefixDiscussion');
        $sender->setData(
            'Discussion.Name',
            wrap(
                $prefix,
                'span',
                ['class' => 'PrefixDiscussion Sp'.str_replace(' ', '_', $prefix)]
            ).$discussion->Name
        );
    }

    /**
     * Add prefix to discussions lists.
     *
     * Does not work for table view since there is no appropriate event
     * in Vanilla 2.1.
     *
     * @param GardenController $sender Sending controller instance.
     * @param array            $args   Event arguments.
     *
     * @package PrefixDiscussion
     * @since 0.1
     *
     * @return  void.
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
            ['class' => 'PrefixDiscussion Sp'.str_replace(' ', '_', $prefix)]
        ).$args['Discussion']->Name;
    }

    /**
     * Add css to discussions list if needed.
     *
     * @param DiscussionsController $sender Sending controller instance.
     *
     * @package PrefixDiscussion
     * @since 0.1
     * @return  void.
     */
    public function discussionsController_render_before($sender) {
        $sender->addCssFile('prefixdiscussion.css', 'plugins/prefixDiscussion');
    }

    /**
     * Add css to categories list if needed.
     *
     * @param CategoriesController $sender Sending controller instance.
     *
     * @package PrefixDiscussion
     * @since 0.1
     * @return  void.
     */
    public function categoriesController_render_before($sender) {
        $sender->addCssFile('prefixdiscussion.css', 'plugins/prefixDiscussion');
    }

    /**
     * Prevent from mixing NULLs and empty strings in the DB
     *
     * @param DiscussionModel $sender Sending controller instance.
     * @param array           $args   Event arguments.
     *
     * @package PrefixDiscussion
     * @since 1.1
     * @return  void.
     */
    public function discussionModel_beforeSaveDiscussion_handler($sender, $args) {
        if (!in_array($args['FormPostValues']['Prefix'], self::getPrefixes())) {
            $args['FormPostValues']['Prefix'] = null;
        }
    }
}
