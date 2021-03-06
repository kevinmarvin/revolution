<?php
/**
 * @package modx
 * @subpackage controllers.resource.symlink
 */
if (!$modx->hasPermission('edit_document')) return $modx->error->failure($modx->lexicon('access_denied'));

if (isset($_REQUEST['template'])) $resource->set('template',$_REQUEST['template']);

/* invoke OnDocFormPrerender event */
$onDocFormPrerender = $modx->invokeEvent('OnDocFormPrerender',array(
    'id' => $resource->get('id'),
    'resource' => &$resource,
    'mode' => modSystemEvent::MODE_UPD,
));
if (is_array($onDocFormPrerender)) $onDocFormPrerender = implode('',$onDocFormPrerender);
$onDocFormRender = str_replace(array('"',"\n","\r"),array('\"','',''),$onDocFormRender);
$modx->smarty->assign('onDocFormPrerender',$onDocFormPrerender);

/* handle default parent */
$parentname = $context->getOption('site_name', '', $modx->_userConfig);
if ($resource->get('parent') != 0) {
    $parent = $modx->getObject('modResource',$resource->get('parent'));
    if ($parent != null) {
        $parentname = $parent->get('pagetitle');
    }
}
$modx->smarty->assign('parent',$resource->get('parent'));
$modx->smarty->assign('parentname',$parentname);

/* invoke OnDocFormRender event */
$onDocFormRender = $modx->invokeEvent('OnDocFormRender',array(
    'id' => $resource->get('id'),
    'resource' => &$resource,
    'mode' => modSystemEvent::MODE_UPD,
));
if (is_array($onDocFormRender)) {
    $onDocFormRender = implode('',$onDocFormRender);
}
$modx->smarty->assign('onDocFormRender',$onDocFormRender);

/* get url for resource for preview window */
$url = $modx->makeUrl($resource->get('id'));

/* assign symlink to smarty */
$modx->smarty->assign('resource',$resource);

/* check permissions */
$publish_document = $modx->hasPermission('publish_document');
$access_permissions = $modx->hasPermission('access_permissions');

/*
 *  Initialize RichText Editor
 */
/* Set which RTE */
$rte = isset($_REQUEST['which_editor']) ? $_REQUEST['which_editor'] : $context->getOption('which_editor', '', $modx->_userConfig);
$modx->smarty->assign('which_editor',$rte);
if ($context->getOption('use_editor', false, $modx->_userConfig) && !empty($rte)) {
    /* invoke OnRichTextEditorRegister event */
    $text_editors = $modx->invokeEvent('OnRichTextEditorRegister');
    $modx->smarty->assign('text_editors',$text_editors);

    $replace_richtexteditor = array('ta');
    $modx->smarty->assign('replace_richtexteditor',$replace_richtexteditor);

    /* invoke OnRichTextEditorInit event */
    $onRichTextEditorInit = $modx->invokeEvent('OnRichTextEditorInit',array(
        'editor' => $rte,
        'elements' => $replace_richtexteditor,
        'id' => $resource->get('id'),
        'resource' => &$resource,
        'mode' => modSystemEvent::MODE_UPD,
    ));
    if (is_array($onRichTextEditorInit)) {
        $onRichTextEditorInit = implode('',$onRichTextEditorInit);
        $modx->smarty->assign('onRichTextEditorInit',$onRichTextEditorInit);
    }
}

/* register FC rules */
$record = $resource->toArray();
$overridden = $this->checkFormCustomizationRules($resource);
$record = array_merge($record,$overridden);

$record['parent_pagetitle'] = $parent ? $parent->get('pagetitle') : '';

/* register JS scripts */
$managerUrl = $context->getOption('manager_url', MODX_MANAGER_URL, $modx->_userConfig);
$modx->regClientStartupScript($managerUrl.'assets/modext/util/datetime.js');
$modx->regClientStartupScript($managerUrl.'assets/modext/widgets/element/modx.panel.tv.renders.js');
$modx->regClientStartupScript($managerUrl.'assets/modext/widgets/resource/modx.grid.resource.security.js');
$modx->regClientStartupScript($managerUrl.'assets/modext/widgets/resource/modx.panel.resource.tv.js');
$modx->regClientStartupScript($managerUrl.'assets/modext/widgets/resource/modx.panel.resource.symlink.js');
$modx->regClientStartupScript($managerUrl.'assets/modext/sections/resource/symlink/update.js');
$modx->regClientStartupHTMLBlock('
<script type="text/javascript">
// <![CDATA[
MODx.config.publish_document = "'.$publish_document.'";
MODx.onDocFormRender = "'.$onDocFormRender.'";
MODx.ctx = "'.$resource->get('context_key').'";
Ext.onReady(function() {
    MODx.load({
        xtype: "modx-page-symlink-update"
        ,resource: "'.$resource->get('id').'"
        ,record: '.$modx->toJSON($record).'
        ,which_editor: "'.$which_editor.'"
        ,access_permissions: "'.$access_permissions.'"
        ,publish_document: "'.$publish_document.'"
        ,preview_url: "'.$url.'"
        ,canSave: "'.($modx->hasPermission('save_document') ? 1 : 0).'"
        ,canEdit: "'.($modx->hasPermission('edit_document') ? 1 : 0).'"
        ,canCreate: "'.($modx->hasPermission('new_document') ? 1 : 0).'"
    });
});
// ]]>
</script>');


$this->checkFormCustomizationRules($resource);
return $modx->smarty->fetch('resource/symlink/update.tpl');
