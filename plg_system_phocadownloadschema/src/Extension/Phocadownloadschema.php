<?php
/**
 * @package     Phoca Download Schema Plugin
 * @version     1.0
 * @license     GNU General Public License version 2
 */
namespace Naftee\Plugin\System\Phocadownloadschema\Extension;
defined('_JEXEC') or die;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Event\Application\BeforeCompileHeadEvent;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Component\ComponentHelper;
class Phocadownloadschema extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeCompileHead' => 'injectPhocaDownloadSchema',
        ];
    }
    public function injectPhocaDownloadSchema(BeforeCompileHeadEvent $event)
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }
        // Phoca Download component must be installed and enabled.
        if (!ComponentHelper::isEnabled('com_phocadownload', true)) {
            return;
        }
        $input = $app->getInput();
        if ($input->get('option') !== 'com_phocadownload') {
            return;
        }
        // Load Phoca libraries.
        if (!class_exists('PhocaDownloadLoader')) {
            require_once JPATH_ADMINISTRATOR . '/components/com_phocadownload/libraries/loader.php';
        }
        phocadownloadimport('phocadownload.access.access');
        $view = $input->get('view');
        $id   = $input->getInt('id');
        $document = $event->getDocument();
        // Individual file
        if ($view === 'file' && $id) {
            $this->injectFileSchema($id, $document);
        }
        // Individual category
        elseif ($view === 'category' && $id) {
            $this->injectCategorySchema($id, $document);
        }
        // Category index
        elseif ($view === 'categories') {
            $this->injectCategoriesSchema($document);
        }
    }
    /**
     * Schema for an individual Phoca Download file.
     */
    private function injectFileSchema(int $id, $document)
    {
        $db       = Factory::getContainer()->get('DatabaseDriver');
        $user     = Factory::getUser();
        $now      = Factory::getDate()->toSql();
        $nullDate = $db->getNullDate();
        // User access levels for SQL filter.
        $userLevels = $user->getAuthorisedViewLevels();
        $userLevelsSql = implode(',', array_map('intval', $userLevels));
        /// Build the query so that only files the current user is allowed to see are returned, checking publication, approval, access levels, and publication dates.
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('f.id'),
                $db->quoteName('f.title'),
                $db->quoteName('f.description'),
                $db->quoteName('f.filename'),
                $db->quoteName('f.date'),
                $db->quoteName('f.version'),
                $db->quoteName('f.access', 'file_access'),
                $db->quoteName('c.title', 'category_title'),
                $db->quoteName('c.access', 'cat_access'),
                $db->quoteName('c.accessuserid', 'cat_accessuserid'),
            ])
            ->from($db->quoteName('#__phocadownload', 'f'))
            ->innerJoin(
                $db->quoteName('#__phocadownload_categories', 'c')
                . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('f.catid')
            )
            ->where($db->quoteName('f.id') . ' = ' . (int) $id)
            ->where($db->quoteName('f.published') . ' = 1')
            ->where($db->quoteName('f.approved') . ' = 1')
            ->where($db->quoteName('c.published') . ' = 1')
            ->where($db->quoteName('c.access') . ' IN (' . $userLevelsSql . ')')
            ->where(
                '(' . $db->quoteName('f.publish_up') . ' = ' . $db->quote($nullDate)
                . ' OR ' . $db->quoteName('f.publish_up') . ' <= ' . $db->quote($now) . ')'
            )
            ->where(
                '(' . $db->quoteName('f.publish_down') . ' = ' . $db->quote($nullDate)
                . ' OR ' . $db->quoteName('f.publish_down') . ' >= ' . $db->quote($now) . ')'
            );
        $db->setQuery($query);
        $file = $db->loadObject();
        if (!$file) {
            return;
        }
        // Check whether the current user has access to the file based on the category's access-user-ID settings.
        $rightDisplay = \PhocaDownloadAccess::getUserRight(
            'accessuserid',
            $file->cat_accessuserid,
            $file->cat_access,
            $user->getAuthorisedViewLevels(),
            $user->get('id', 0),
            0
        );
        if ($rightDisplay == 0) {
            return;
        }
        // File-level access.
        if (!in_array((int) $file->file_access, $user->getAuthorisedViewLevels(), true)) {
            return;
        }
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'DigitalDocument',
            'name'     => $file->title,
            'url'      => $this->makeAbsoluteUrl(
                Uri::getInstance()->toString()
            ),
        ];
        if (!empty($file->category_title)) {
            $schema['category'] = $file->category_title;
        }
        if (!empty($file->description)) {
            $schema['description'] = trim(strip_tags($file->description));
        }
        if (!empty($file->date) && $file->date !== $nullDate) {
            $schema['datePublished'] = Factory::getDate($file->date)->toISO8601();
        }
        if (!empty($file->version)) {
            $schema['version'] = $file->version;
        }
        $this->addSchemaToHead($schema, $document);
    }
    /**
     * Schema for an individual Phoca Download category.
     */
    private function injectCategorySchema(int $id, $document)
    {
        $db       = Factory::getContainer()->get('DatabaseDriver');
        $user     = Factory::getUser();
        $now      = Factory::getDate()->toSql();
        $nullDate = $db->getNullDate();
        $userLevels = $user->getAuthorisedViewLevels();
        $userLevelsSql = implode(',', array_map('intval', $userLevels));
        // Get the category itself.
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('description'),
                $db->quoteName('access'),
                $db->quoteName('accessuserid'),
            ])
            ->from($db->quoteName('#__phocadownload_categories'))
            ->where($db->quoteName('id') . ' = ' . (int) $id)
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . $userLevelsSql . ')');
        $db->setQuery($query);
        $category = $db->loadObject();
        if (!$category) {
            return;
        }
        // Check the category's access-user-ID restrictions.
        $rightDisplay = \PhocaDownloadAccess::getUserRight(
            'accessuserid',
            $category->accessuserid,
            $category->access,
            $user->getAuthorisedViewLevels(),
            $user->get('id', 0),
            0
        );
        if ($rightDisplay == 0) {
            return;
        }
        /// Get files belonging to this category, applying publication, approval, access-level, and publication-date restrictions.
        $filesQuery = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('access'),
            ])
            ->from($db->quoteName('#__phocadownload'))
            ->where($db->quoteName('catid') . ' = ' . (int) $id)
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('approved') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . $userLevelsSql . ')')
            ->where(
                '(' . $db->quoteName('publish_up') . ' = ' . $db->quote($nullDate)
                . ' OR ' . $db->quoteName('publish_up') . ' <= ' . $db->quote($now) . ')'
            )
            ->where(
                '(' . $db->quoteName('publish_down') . ' = ' . $db->quote($nullDate)
                . ' OR ' . $db->quoteName('publish_down') . ' >= ' . $db->quote($now) . ')'
            )
            ->order($db->quoteName('ordering') . ' ASC');
        $db->setQuery($filesQuery);
        $files = $db->loadObjectList();
        // Extra safety: remove any file whose access level is not available to the current user.
        $files = array_filter($files, function ($file) use ($user) {
            return in_array((int) $file->access, $user->getAuthorisedViewLevels(), true);
        });
        $files = array_values($files);
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'CollectionPage',
            'name'     => $category->title,
            'url'      => $this->makeAbsoluteUrl(
                Uri::getInstance()->toString()
            ),
        ];
        if (!empty($category->description)) {
            $schema['description'] = trim(strip_tags($category->description));
        }
        if (!empty($files)) {
            $itemList = [];
            foreach ($files as $index => $file) {
                $fileUrl = Route::_('index.php?option=com_phocadownload&view=category&id=' . (int) $id . '&download=' . (int) $file->id . ':' . \Joomla\CMS\Filter\OutputFilter::stringURLSafe($file->title));
                $itemList[] = [
                    '@type'    => 'ListItem',
                    'position' => $index + 1,
                    'name'     => $file->title,
                    'url'      => $this->makeAbsoluteUrl($fileUrl),
                ];
            }
            $schema['mainEntity'] = [
                '@type'           => 'ItemList',
                'itemListElement' => $itemList,
            ];
        }
        $this->addSchemaToHead($schema, $document);
    }
    /**
     * Schema for the Phoca Download category index
     */
    private function injectCategoriesSchema($document)
    {
        $db   = Factory::getContainer()->get('DatabaseDriver');
        $user = Factory::getUser();
        $userLevels = $user->getAuthorisedViewLevels();
        $userLevelsSql = implode(',', array_map('intval', $userLevels));
        /// Get published categories accessible to the current user.
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('description'),
                $db->quoteName('access'),
                $db->quoteName('accessuserid'),
            ])
            ->from($db->quoteName('#__phocadownload_categories'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . $userLevelsSql . ')')
            ->order($db->quoteName('ordering') . ' ASC');
        $db->setQuery($query);
        $categories = $db->loadObjectList();
        $itemList = [];
        foreach ($categories as $category) {
            // Check Phoca's access-user-ID restrictions.
            $rightDisplay = \PhocaDownloadAccess::getUserRight(
                'accessuserid',
                $category->accessuserid,
                $category->access,
                $userLevels,
                $user->get('id', 0),
                0
            );
            if ($rightDisplay == 0) {
                continue;
            }
            // Generate the actual Joomla/Phoca category URL, respecting SEF routing.
            $categoryUrl = Route::_(
                'index.php?option=com_phocadownload&view=category&id=' . (int) $category->id
            );
            $itemList[] = [
                '@type'    => 'ListItem',
                'position' => count($itemList) + 1,
                'name'     => $category->title,
                'url'      => $this->makeAbsoluteUrl($categoryUrl),
            ];
        }
        $documentTitle = $document->getTitle();
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'CollectionPage',
            'name'     => $documentTitle,
            'url'      => $this->makeAbsoluteUrl(
                Uri::getInstance()->toString()
            ),
        ];
        if (!empty($itemList)) {
            $schema['mainEntity'] = [
                '@type'           => 'ItemList',
                'itemListElement' => $itemList,
            ];
        }
        $this->addSchemaToHead($schema, $document);
    }
    /**
     * Convert a Joomla relative route into an absolute URL.
     */
    private function makeAbsoluteUrl(string $url): string
    {
        $url = trim($url);
        // Leave absolute HTTP and HTTPS URLs unchanged.
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        // Convert protocol-relative URLs to HTTPS.
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        // Convert relative URLs to absolute URLs.
        return rtrim(Uri::root(), '/') . '/' . ltrim($url, '/');
    }
    /**
     * Add JSON-LD to the document head.
     */
    private function addSchemaToHead(array $schema, $document)
    {
        $json = json_encode(
            $schema,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if ($json === false) {
            return;
        }
        $document->addCustomTag(
            '<script type="application/ld+json">' . $json . '</script>'
        );
    }
}
