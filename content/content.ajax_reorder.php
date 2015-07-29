<?php
/**
 * @package content
 */
/**
 * The AjaxReorder page is used for reordering objects in the Symphony
 * backend through Javascript. At the moment this is only supported for
 * Pages and Sections.
 */
require_once TOOLKIT . '/class.xmlpage.php';

class contentExtensionURL_ConnectorAjax_Reorder extends XMLPage
{
    public function view()
    {
        $items = $_REQUEST['items'];
        if (!is_array($items) or empty($items)) {
            return;
        }

        foreach ($items as $id => $position) {
            if (!is_numeric($id)) {
                return false;
            }

            if (!Symphony::Database()->update(array('sortorder' => $position), 'tbl_url_connections', sprintf("`id` = %d", $id))) {
                $this->setHttpStatus(self::HTTP_STATUS_ERROR);
                $this->_Result->setValue(__('A database error occurred while attempting to reorder.'));
            }
        }
        //$this->setHttpStatus(self::HTTP_STATUS_BAD_REQUEST);
    }
}
