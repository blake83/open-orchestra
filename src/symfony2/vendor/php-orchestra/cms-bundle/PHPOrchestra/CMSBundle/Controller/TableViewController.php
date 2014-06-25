<?php
/**
 * This file is part of the PHPOrchestra\CMSBundle.
 *
 * @author Nicolas ANNE <nicolas.anne@businessdecision.com>
 */

namespace PHPOrchestra\CMSBundle\Controller;

use PHPOrchestra\CMSBundle\Exception\NonExistingFieldException;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

abstract class TableViewController extends Controller
{
	
	protected $columns = array();
    protected $values = array();
    protected $entity = null;
    protected $criteria = array();
    protected $sort = array();
    protected $key = null;
    protected $callback = array();
    protected $buttonTwig = 'PHPOrchestraCMSBundle:BackOffice/TableView:button.html.twig';
    protected $routeParameters = array();
    protected $title = '';
    
    abstract public function setColumns();
    
    function __construct() {
        $this->setColumns();
        $this->callback['arrayToNewLine'] = function($value){return implode('<br />', $value);};
        $this->callback['replaceComaByNewLine'] = function($value){return implode('<br />', explode(',', $value));};
    }
        
    public function init(){}

    public function getColumns()
    {
        return $this->columns;
    }
    
    public function setValues($values){
        $this->values = $values;
    }
    
    public function getValues(){
        return $this->values;
    }
    
    public function setEntity($entity){
        $this->entity = $entity;
    }
    
    public function getEntity(){
        return $this->entity;
    }
    
    public function setCriteria($criteria){
        $this->criteria = $criteria;
    }
    
    public function getCriteria($criteria){
        return $this->criteria;
    }
        
    public function setSort($sort){
        $this->sort = $sort;
    }
    
    public function getSort($sort){
        return $this->sort;
    }
    
    public function setKey($key){
    	if(is_array($key)){
            $this->key = $key;
    	}
    }
    
    public function getKey(){
        return $this->key;
    }
    
    /**
     * Set table title
     * 
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }
    
    /**
     * Get table title
     */
    public function getTitle()
    {
        if ($this->title != '') {
            return $this->title;
        } else {
            return $this->getEntity();
        }
    }
    
    public function setButtonTwig($buttonTwig){
        $this->buttonTwig = $buttonTwig;
    }
    
    public function getButtonTwig($buttonTwig){
        return $this->buttonTwig;
    }
    
    /**
     * @Route("/{action}/{id}")
     */
    public function indexAction(Request $request, $action, $id = null){
        $this->routeParameters = $request->attributes->get('_route_params');
        $this->init();
        return call_user_func(array($this, $action.'Action'), $request, $id);
    }
    
    public function editAction(Request $request, $id = null){
    	if($this->getEntity() !== null){
            return $this->editEntity($request, $id);
        }
        else{
            return $this->edit($id);
        }
    }

    public function deleteAction(Request $request, $id = null){
    	if($this->getEntity() !== null){
            return $this->deleteEntity($request, $id);
        }
        else{
            return $this->edit($id);    
        }
    }

    public function catalogAction(Request $request)
    {
        if($this->getEntity() !== null){
            return $this->catalogEntity($request);
        } else {
            return $this->catalog($request);
        }
    }
    
    
    public function generateUrlValue($action, $id = false) {
        $parameters = $this->routeParameters;
        $parameters['action'] = $action;
        if(!empty($id)){
            $parameters['id'] = $id;
        } else {
            unset($parameters['id']);
        }
        return $this->generateUrl($this->get('request')->get('_route'), $parameters);
    }
    
    public function genericButton($data, $action, $label, $class, $icon){
        if($this->getEntity() !== null){
        	$data = $this->generateUrlValue($action, $data);
        }
        $render = $this->render(
            $this->buttonTwig,
            array(
                'data' => $data,
                'label' => $label,
                'class' => $class,
                'icon' => $icon
            )
        );
        return $render->getContent();    	
    }
    
    public function modifyButton($value){
    	return $this->genericButton($value, 'edit', 'Modifier', 'btn btn-primary redirect', 'fa fa-edit');
    }
    public function deleteButton($value){
        return $this->genericButton($value, 'delete', 'Supprimer', 'btn btn-danger delete', 'fa fa-trash-o');
    }
    public function addButton(){
        return $this->genericButton('', 'edit', 'Ajouter', 'btn btn-small btn-ribbon bt-primary redirect', 'fa fa-plus');
    }
    public function saveButton($value){
        return $this->genericButton($value, 'edit', 'Enregistrer', 'btn btn-small btn-ribbon bt-primary submit', 'fa fa-save');
    }
    public function backButton(){
        return $this->genericButton('', 'catalog', 'Retour', 'btn btn-small btn-ribbon bt-primary redirect', 'fa fa-undo');
    }
    
    public function format(){
        $values = $this->getValues();
        $columns = $this->getColumns();
        foreach($values as &$record){
        	$newRecord = array();
        	foreach($columns as $column){
        		if(array_key_exists('button', $column)){
	        		if($column['button'] == 'modify'){
	        			$newRecord[] = $this->modifyButton($record['id']->__toString());
	        		}
	        		else if($column['button'] == 'delete'){
	        			$newRecord[] = $this->deleteButton($record['id']->__toString());
	        		}
        		}
        		else{
                    if (!array_key_exists($column['name'], $record)) {
                        throw new NonExistingFieldException('The field ' . $column['name'] . ' does not exist in TableViewController.php');
                    }
        			if(array_key_exists('callback', $column)){
        		        $newRecord[] = $this->callback[$column['callback']]($record[$column['name']]);
        			}
        			else{
        				$newRecord[] = $record[$column['name']];
        			}
        		}
        	}
        	$record = $newRecord;
        }
        $this->setValues($values);
    }

    public function editEntity(Request $request, $id)
    {
        $document = $this->getDocument($id);
        $document = $this->modifyDocumentAfterGet($document);
        
        $form = $this->createEditForm($request, $document);
        
        return $this->editRender($request, $id, $form, $document);
    }

    /**
     * Get an existing document by its $id or create a new one
     * 
     * @param string $id
     */
    protected function getDocument($id)
    {
        $documentManager = $this->container->get('phporchestra_cms.documentmanager');
        if (empty($id)) {
            $document = $documentManager->createDocument($this->getEntity());
            $document = $this->modifyDocumentAfterCreate($document);
        } else {
            if ($this->getKey() === null) {
                $document = $documentManager->getDocumentById($this->getEntity(), $id);
            } else {
                $criteria = array_combine($this->getKey(), explode('|', $id));
                $document = $documentManager->getDocument($this->getEntity(), $criteria);
            }
        }
        return $document;
    }

    /**
     * Allow to modify a loaded $document after its creation
     * 
     * @param unknown_type $document of $this->entity type
     */
    protected function modifyDocumentAfterCreate($document)
    {
        return $document;
    }

    /**
     * Allow to modify a loaded $document before passing it to the form
     * 
     * @param unknown_type $document of $this->entity type
     */
    protected function modifyDocumentAfterGet($document)
    {
        return $document;
    }

    /**
     * Return the edit form
     * 
     * @param $document
     */
    protected function createEditForm($request, $document)
    {
    	if ($request->query->get('refresh') !== null) {
        	$name = lcfirst($this->getEntity());
	        $form = $this->createForm(
	            $name,
	            $request->query->get($name),
	            array(
	               'action' => $this->getRequest()->getUri(),
	            )
	        );
        }
        else{
	        $form = $this->createForm(
	            lcfirst($this->getEntity()),
	            $document,
	            array(
	               'action' => $this->getRequest()->getUri(),
	            )
	        );
        }
        
        return $form;
    }

    /**
     * Factorize Render for json and no json response
     * 
     * @param Request $request
     * @param string $id
     * @param unknown_type $form
     * @param unknown_type $document of $this->entity type
     */
    protected function getRender($id, $form)
    {
        return $this->render(
            'PHPOrchestraCMSBundle:BackOffice/TableView:form.html.twig',
            array(
                'form' => $form->createView(),
                'title' => $this->getTitle(),
                'ribbon' => $this->saveButton($id) . $this->backButton()
            )
        );
    }
    
    /**
     * Render the view, either edit form or catalog list
     * 
     * @param Request $request
     * @param string $id
     * @param unknown_type $form
     * @param unknown_type $document of $this->entity type
     */
    protected function editRender($request, $id, $form, $document)
    {
        if ($request->query->get('refresh') !== null) {
            $render = $this->render(
                'PHPOrchestraCMSBundle:Form:form.html.twig',
                array(
                    'form' => $form->createView()
                )
            );
            return new JsonResponse(
                array(
                    'success' => true,
                    'data' => $render->getContent()
                )
            );
        }
        else{
        	if ($request->getMethod() == 'POST') {
	        	$form->handleRequest($request);
	            $success = false;
	            if ($form->isValid()) {
	                $document = $this->modifyDocumentBeforeSave($document);
	                $document->save();
	                $saveResult = $this->afterSave($document);
	                $success = $saveResult['success'];
	                $data = $saveResult['data'];
	            }
	            else{
                    $data = $this->getRender($id, $form)->getContent();	            	
	            }
	            return new JsonResponse(
	                array(
	                    'success' => $success,
	                    'data' => $data
	                )
	            );
	        }
	        
	        return $this->getRender($id, $form);
        }
    }
    
    /**
     * Allows to modify document after form validation and before saving it
     * 
     * @param unknown_type $document of $this->entity type
     */
    protected function modifyDocumentBeforeSave($document)
    {
        return $document;
    }
    
    /**
     * Allows to do some additionnal stuff after saving the $document
     * 
     * @param unknown_type $document of $this->entity type
     */
    protected function afterSave($document)
    {
        return array(
            'success' => true,
            'data' => $this->generateUrlValue('catalog')
        );
    }
    
    public function deleteEntity(Request $request, $id)
    {
        $documentManager = $this->container->get('phporchestra_cms.documentmanager');
        if(!empty($id)) {
            if($this->getKey() === null){
                $document = $documentManager->getDocumentById($this->getEntity(), $id);
            }
            else{
                $criteria = array_combine($this->getKey(), explode('|', $id));
                $document = $documentManager->getDocument($this->getEntity(), $criteria);
            }
        	$document->delete();
        }
        
        return $this->redirect($this->generateUrlValue('catalog'));
    }
    public function catalogEntity(Request $request)
    {
        $documentManager = $this->container->get('phporchestra_cms.documentmanager');
    	if ($request->get('parse')) {
            
            $sort = is_array($request->get('sort')) ? $request->get('sort') : $this->sort;
            $sort = array_map('intval', $sort);

            parse_str($request->get('criteria'), $criteria);
            $criteria = array_merge($this->criteria, $criteria);
            array_walk($criteria, function(&$value, $key) {
                $value = new \MongoRegex('/'.preg_quote($value).'/i');
            });
            
            $this->setValues($documentManager->getDocuments($this->getEntity(), $criteria, $sort, true, $request->get('start'), $request->get('length')));
            $this->format();
            
            $count = $documentManager->getDocumentsCount($this->getEntity());
            
            $partialCount = $documentManager->getDocumentsCount($this->getEntity(), $criteria);
            
            return new JsonResponse(
                array(
                    'success' => true,
                    'data' => array(
                        'values' => $this->values,
                        'count' => $count,
                        'partialCount' => $partialCount
                    )
                )
            );
        } else {
        	
            return $this->render('PHPOrchestraCMSBundle:BackOffice:tableViewLayout.html.twig',
                array(
                    'columns' => $this->getColumns(),
                    'listUrl' => $this->generateUrlValue('catalog'),
                    'order' => array(array(1, 'desc')),
                    'title' => $this->getTitle(),
                    'ribbon' => $this->addButton()
                )
            );
        }
    }
}
