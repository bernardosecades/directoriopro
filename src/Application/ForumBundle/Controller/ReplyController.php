<?php

namespace Application\ForumBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Application\ForumBundle\Entity\Reply;
use Application\ForumBundle\Form\ReplyType;

/**
 * Reply controller.
 *
 * @Route("/reply")
 */
class ReplyController extends Controller
{
    /**
     * Lists all Reply entities.
     *
     * @Route("/", name="reply")
     * @Template()
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entities = $em->getRepository('ApplicationForumBundle:Reply')->findAll();

        return array('entities' => $entities);
    }

    /**
     * Finds and displays a Reply entity.
     *
     * @Route("/{id}/show", name="reply_show")
     * @Template()
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('ApplicationForumBundle:Reply')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Reply entity.');
        }

        

        return array(
            'entity' => $entity,
            );
    }

    /**
     * Displays a form to create a new Reply entity.
     *
     * @Route("/new", name="reply_new")
     * @Template()
     */
    public function newAction()
    {
        $entity = new Reply();
        $form   = $this->createForm(new ReplyType(), $entity);

        return array(
            'entity' => $entity,
            'form'   => $form->createView()
        );
    }

    /**
     * Creates a new Reply entity.
     *
     * @Route("/create", name="reply_create")
     * @Method("post")
     * @Template("ApplicationForumBundle:Reply:new.html.twig")
     */
    public function createAction()
    {

        $session = $this->getRequest()->getSession();
        $session_id = $session->get('id');
        if ( !$session_id ) {
            return $this->redirect($this->generateUrl('forum'));
        }

        $entity  = new Reply();
        $request = $this->getRequest();
        $form    = $this->createForm(new ReplyType(), $entity);
        $form->bindRequest($request);

        // rellenar campos que faltan
        $entity->setUserId( $session_id );
        $entity->setDate( new \DateTime("now") );

        if ($form->isValid()) {

            // limpiar html
            $entity->setBody( strip_tags( $entity->getBody() ) );

            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($entity);
            $em->flush();

            // contar respuestas
            $em = $this->getDoctrine()->getEntityManager();
            $thread = $em->getRepository('ApplicationForumBundle:Thread')->find( $entity->getThreadId() );
            $thread->setReplies($thread->getReplies() + 1 );

            // actualizar fecha
            $thread->setDateUpdate( new \DateTime("now") );
            $em->persist($thread);
            $em->flush();

            return $this->redirect($this->generateUrl('thread_show', array('id' => $thread->getId(), 'slug' => $thread->getSlug(), 'forum_id' => $thread->getForumId())));
            
        }

        return array(
            'entity' => $entity,
            'form'   => $form->createView()
        );
    }

    /**
     * Displays a form to edit an existing Reply entity.
     *
     * @Route("/{id}/edit", name="reply_edit")
     * @Template()
     */
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('ApplicationForumBundle:Reply')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Reply entity.');
        }

        $thread = $em->getRepository('ApplicationForumBundle:Thread')->find( $entity->getThreadId() );

        $session = $this->getRequest()->getSession();
        $user_id = $session->get('id');
        $admin = $session->get('admin');

        if ( ( $entity->getUserId() == $user_id ) || $admin ) {

            $editForm = $this->createForm(new ReplyType(), $entity);

            return array(
                'entity'      => $entity,
                'edit_form'   => $editForm->createView(),
                'thread'      => $thread,
            );

        }else{

            return $this->redirect($this->generateUrl('thread_show', array('id' => $thread->getId(), 'slug' => $thread->getSlug(), 'forum_id' => $thread->getForumId())));
        }

    }

    /**
     * Edits an existing Reply entity.
     *
     * @Route("/{id}/update", name="reply_update")
     * @Method("post")
     * @Template("ApplicationForumBundle:Reply:edit.html.twig")
     */
    public function updateAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('ApplicationForumBundle:Reply')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Reply entity.');
        }


        $session = $this->getRequest()->getSession();
        $user_id = $session->get('id');
        $admin = $session->get('admin');

        $thread = $em->getRepository('ApplicationForumBundle:Thread')->find( $entity->getThreadId() );

        if ( ( $entity->getUserId() == $user_id ) || $admin ) {

            $editForm   = $this->createForm(new ReplyType(), $entity);
            

            $request = $this->getRequest();

            $editForm->bindRequest($request);

            if ($editForm->isValid()) {


                // limpiar html
                $entity->setBody( strip_tags( $entity->getBody() ) );

                $em->persist($entity);
                $em->flush();

                return $this->redirect($this->generateUrl('thread_show', array('id' => $thread->getId(), 'slug' => $thread->getSlug(), 'forum_id' => $thread->getForumId())));
            }

            return array(
                'entity'      => $entity,
                'edit_form'   => $editForm->createView(),
                
            );

        }else{

            return $this->redirect($this->generateUrl('thread_show', array('id' => $thread->getId(), 'slug' => $thread->getSlug(), 'forum_id' => $thread->getForumId())));
        }
    }

    /**
     * Deletes a Reply entity.
     *
     * @Route("/{id}/delete", name="reply_delete")
     */
    public function deleteAction($id)
    {


        $em = $this->getDoctrine()->getEntityManager();
        $entity = $em->getRepository('ApplicationForumBundle:Reply')->find($id);
        if (!$entity) {
          throw $this->createNotFoundException('Unable to find Post entity.');
        }

        $session = $this->getRequest()->getSession();
        $user_id = $session->get('id');
        $admin = $session->get('admin');

        $thread = $em->getRepository('ApplicationForumBundle:Thread')->find( $entity->getThreadId() );

        if ( ( $entity->getUserId() == $user_id ) || $admin ) {

            $em->remove($entity);
            $em->flush();

            // contar forum total replies
            $thread->setReplies($thread->getReplies() - 1 );
            $em->persist($thread);
            $em->flush();

        }
        
        
        return $this->redirect($this->generateUrl('thread_show', array('id' => $thread->getId(), 'slug' => $thread->getSlug(), 'forum_id' => $thread->getForumId())));
        
    }


}
