<?php

namespace Application\AnunciosBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Application\AnunciosBundle\Entity\Post;
use Application\UserBundle\Entity\User;
use Application\UserBundle\Entity\Contact;
use Application\AnunciosBundle\Form\PostType;
use Application\UserBundle\Form\ContactType;
//use Symfony\Component\HttpFoundation\Request;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\View\DefaultView;
use Pagerfanta\Adapter\DoctrineORMAdapter;

define('CAT_OTHER',9);

/**
 * Post controller.
 *
 * @Route("/post")
 */
class PostController extends Controller
{
    /**
     * Lists all Post entities.
     *
     * @Route("/", name="post")
     * @Template()
     */
    public function indexAction()
    {
		$request = $this->getRequest();
		$page = $request->query->get('page');
		if( !$page ) $page = 1;
	
        $em = $this->getDoctrine()->getEntityManager();



		$query = $em->createQueryBuilder();
		$query->add('select', 'p')
		   ->add('from', 'ApplicationAnunciosBundle:Post p')
		   ->add('where', 'p.type != 2')
		   ->add('orderBy', 'p.featured DESC, p.id DESC');
		
		// categoria?
		$category_id = $request->query->get('c');
		if( $category_id ){
		   $query->add('where', 'p.category_id = :category_id')->setParameter('category_id', $category_id);

		}
		
		
        $adapter = new DoctrineORMAdapter($query);

		$pagerfanta = new Pagerfanta($adapter);
		$pagerfanta->setMaxPerPage(10); // 10 by default
		$maxPerPage = $pagerfanta->getMaxPerPage();

		$pagerfanta->setCurrentPage($page); // 1 by default
		$entities = $pagerfanta->getCurrentPageResults();
		$routeGenerator = function($page, $category_id) {
			$url = '?page='.$page;
			if( $category_id ) $url .= '&c=' . $category_id;
		    return $url;
		};

		$view = new DefaultView();
		$html = $view->render($pagerfanta, $routeGenerator, array('category_id' => (int)$category_id));
		

		$users = false;
		if( $page == 1 ){
			$qb = $em->createQueryBuilder();
			$qb->add('select', 'u')
			   ->add('from', 'ApplicationUserBundle:User u')
			   ->add('where', 'u.freelance = 1')
			   ->add('orderBy', 'u.date_login DESC')
			   ->setMaxResults(14);
			
			$query = $qb->getQuery();
			$users = $query->getResult();
			shuffle( $users );
			$users = array_splice($users, 0, 7);
		}

	 	$twig = $this->container->get('twig'); 
	    $twig->addExtension(new \Twig_Extensions_Extension_Text);

        return array('pager' => $html, 'entities' => $entities, 'users' => $users );
    }

    /**
     * Finds and displays a Post entity.
     *
     * @Route("/{id}/show", name="post_show")
     * @Template()
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('ApplicationAnunciosBundle:Post')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Post entity.');
        }

		$user = $em->getRepository('ApplicationUserBundle:User')->find($entity->getUserId());


		$session = $this->getRequest()->getSession();
		$contact = new \Application\UserBundle\Entity\Contact;
		$id = $session->get('id');
		if( $id ){
			$user_login = $em->getRepository('ApplicationUserBundle:User')->find($id);
			$contact->setName( $user_login->getName() );
			$contact->setEmail( $user_login->getEmail() );
		}
		$contact->setSubject( "RE: " . $entity->getTitle() );
		$contact_form = $this->createForm(new ContactType(), $contact);
		$contact_form_html = $contact_form->createView();


		
		$entities = false;
		$users = false;

		// ofertas relacionadas
		if( $entity->getType() == 0 ){
			$query = $em->createQueryBuilder();
			$query->add('select', 'p')
			   ->add('from', 'ApplicationAnunciosBundle:Post p')
			   ->add('where', 'p.category_id = :category_id')->setParameter('category_id', $entity->getCategoryId())
			   ->andWhere('p.id != :id')->setParameter('id', $entity->getId())
			   ->add('orderBy', 'p.id DESC')
			   ->setMaxResults(5);
			$entities = $query->getQuery()->getResult();
			
		}

		/*
		// usuarios relacionados
		$query = $em->createQueryBuilder();
		$query->add('select', 'u')
		   ->add('from', 'ApplicationUserBundle:User u')
		   ->andWhere('u.category_id = :category_id')->setParameter('category_id', $entity->getCategoryId())
		   ->andWhere('u.body IS NOT NULL')
		   ->add('orderBy', 'u.votes DESC, u.id DESC')
		   ->setMaxResults(12);
		
		// empleo
		if( $entity->getType() == 0 ){
			$query->andWhere('u.unemployed = 1');
		
		// freelance
		}else if( $entity->getType() == 1 ){
			$query->andWhere('u.freelance = 1');
		}

		$users = $query->getQuery()->getResult();
		*/


		


		// es diferente usuario, visitas + 1
		$session = $this->getRequest()->getSession();
		$session_id = $session->get('id');
		if( $session_id != $entity->getUserId() ){
			$entity->setVisits($entity->getVisits() + 1 );
			$em->persist($entity);
			$em->flush();
		}

        return array(
            'entity'       => $entity,
            'user'         => $user,
			'contact_form' => $contact_form_html,
			'entities'     => $entities
			//'users'        => $users
			);
    }

    /**
     * Displays a form to create a new Post entity.
     *
     * @Route("/new", name="post_new")
     * @Template()
     */
    public function newAction()
    {
	
		$session = $this->getRequest()->getSession();
		$session_id = $session->get('id');
		if( !$session_id ){
			return $this->redirect($this->generateUrl('user_welcome', array('back' => $_SERVER['REQUEST_URI'])));
		}
		
		//si no es post
		$request = $this->getRequest();
		
		if ($request->getMethod() != 'POST') {
        	$em = $this->getDoctrine()->getEntityManager();
			$user = $em->getRepository('ApplicationUserBundle:User')->find($session_id);
			$email = $user->getEmail();
		}
	
		$type = $request->query->get('type') ? 1 : 0;
		
	
        $entity = new Post();
        $entity->setType($type);
		$entity->setEmail( $email );
        $form   = $this->createForm(new PostType(), $entity);

        return array(
            'entity' => $entity,
            'form'   => $form->createView(),
			'type'   => $type
        );
    }

    /**
     * Creates a new Post entity.
     *
     * @Route("/create", name="post_create")
     * @Method("post")
     * @Template("ApplicationAnunciosBundle:Post:new.html.twig")
     */
    public function createAction()
    {
        $entity  = new Post();
        $request = $this->getRequest();
        $form    = $this->createForm(new PostType(), $entity);
        $form->bindRequest($request);

		// rellenar campos que faltan
		$session = $this->getRequest()->getSession();
		$user_id = $session->get('id');
		$entity->setUserId( $user_id );
		$entity->setDate( new \DateTime("now") );
		$entity->setFeatured( 0 );
		

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('post_show', array('id' => $entity->getId())));
            
        }

        return array(
            'entity' => $entity,
            'form'   => $form->createView()
        );
    }

    /**
     * Displays a form to edit an existing Post entity.
     *
     * @Route("/{id}/edit", name="post_edit")
     * @Template()
     */
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('ApplicationAnunciosBundle:Post')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Post entity.');
        }

		$session = $this->getRequest()->getSession();
		$user_id = $session->get('id');
		$admin = $session->get('admin');
		
		if( ( $entity->getUserId() == $user_id ) || $admin ){

			$editForm = $this->createForm(new PostType(), $entity);

	        return array(
	            'entity'      => $entity,
	            'edit_form'   => $editForm->createView(),
	        );
	
		}else{
			$url = $this->generateUrl('post_show', array('id' => $entity->getId()));
			return $this->redirect($url);
		}
		
		
		
 
    }

    /**
     * Edits an existing Post entity.
     *
     * @Route("/{id}/update", name="post_update")
     * @Method("post")
     * @Template("ApplicationAnunciosBundle:Post:edit.html.twig")
     */
    public function updateAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('ApplicationAnunciosBundle:Post')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Post entity.');
        }

		
		$session = $this->getRequest()->getSession();
		$user_id = $session->get('id');
		$admin = $session->get('admin');
		
		if( ( $entity->getUserId() == $user_id ) || $admin ){

	        $editForm   = $this->createForm(new PostType(), $entity);

	        $request = $this->getRequest();

	        $editForm->bindRequest($request);

	        if ($editForm->isValid()) {
	            $em->persist($entity);
	            $em->flush();

	            return $this->redirect($this->generateUrl('post_show', array('id' => $id)));
	        }

	        return array(
	            'entity'      => $entity,
	            'edit_form'   => $editForm->createView(),
	        );
	
		}else{
			$url = $this->generateUrl('post_show', array('id' => $entity->getId()));
			return $this->redirect($url);
		}
		
		
		
		

    }

    /**
     * Deletes a Post entity.
     *
     * @Route("/{id}/delete", name="post_delete")
     */
    public function deleteAction($id)
    {
		$em = $this->getDoctrine()->getEntityManager();
		$entity = $em->getRepository('ApplicationAnunciosBundle:Post')->find($id);
		if (!$entity) {
            throw $this->createNotFoundException('Unable to find Post entity.');
        }
		
		$session = $this->getRequest()->getSession();
		$user_id = $session->get('id');
		$admin = $session->get('admin');
		
		if( ( $entity->getUserId() == $user_id ) || $admin ){

            $em->remove($entity);
            $em->flush();

			$url = $this->generateUrl('post');
		}else{
			$url = $this->generateUrl('post_show', array('id' => $entity->getId()));
			
		}
		return $this->redirect($url);
    }

    /**
     * Search Post entities.
     *
     * @Route("/search", name="post_search")
     * @Template()
     */
    public function searchAction()
    {
		$request = $this->getRequest();
		$search = $request->query->get('q');
		$category_id = $request->query->get('c');
		$type = (int)$request->query->get('t');
		$location = $request->query->get('location');
		

		$em = $this->getDoctrine()->getEntityManager();
		$qb = $em->createQueryBuilder();
		$qb->add('select', 'p')
		   ->add('from', 'ApplicationAnunciosBundle:Post p')
		   ->add('orderBy', 'p.featured DESC, p.id DESC');
		
		if( $search ) $qb->andWhere("( p.body LIKE '%".$search."%' OR p.title LIKE '%".$search."%' )");
		if( $category_id ) $qb->andWhere('p.category_id = :category_id')->setParameter('category_id', $category_id);
		if( $location ) $qb->andWhere("p.location = :location")->setParameter('location', $location);
		if( $type ) $qb->andWhere('p.type = :type')->setParameter('type', $type);

		$entities = $qb->getQuery()->getResult();
		
	 	$twig = $this->container->get('twig'); 
	    $twig->addExtension(new \Twig_Extensions_Extension_Text);
		
        return array('entities' => $entities, 'form_category' =>$category_id, 'form_type' => $type);
    }

    /**
     * Feed Post entities.
     *
     * @Route("/feed", name="post_feed", defaults={"_format"="xml"})
     * @Template()
     */
    public function feedAction()
    {

		$request = $this->getRequest();

		
		$em = $this->getDoctrine()->getEntityManager();

		$qb = $em->createQueryBuilder()
		   ->add('select', 'p')
		   ->add('from', 'ApplicationAnunciosBundle:Post p')
		   ->add('orderBy', 'p.id DESC')
		   ->setMaxResults(10);
		
		// categoria?
		$category_id = $request->query->get('c');
		if( $category_id ){
		   $qb->andWhere('p.category_id = :category_id')->setParameter('category_id', $category_id);
		}
		
		// tipo?
		$type = (int)$request->query->get('t');
		if( $type ){
		   $qb->andWhere('p.type = :type')->setParameter('type', $type);
		}

		$query = $qb->getQuery();
		$entities = $query->getResult();

		
		
	 	$twig = $this->container->get('twig'); 
	    $twig->addExtension(new \Twig_Extensions_Extension_Text);
		
        return array('entities' => $entities, 'form_category' =>$category_id);
    }

    /**
     * Contact form
     *
     * @Route("/{id}/contact", name="post_contact")
     * @Template()
     */
    public function contactAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();
		$entity = $em->getRepository('ApplicationAnunciosBundle:Post')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }

		$form = $this->createForm(new ContactType());
		$result = 'no';
		
		$request = $this->getRequest();
		if ($request->getMethod() == 'POST') {
	        $form->bindRequest($request);
	
			
			

	        if ($form->isValid()) {

				
				$values = $form->getData();
				
				$toEmail = $entity->getEmail();// 'gafeman@gmail.com';
				
				extract( $values );

				$header = 'From: ' . $email . " \r\n";
				$header .= "X-Mailer: PHP/" . phpversion() . " \r\n";
				$header .= "Mime-Version: 1.0 \r\n";
				$header .= "Content-Type: text/plain";

				$mensaje = "Este mensaje fue enviado por " . $name . ". \r\n";
				$mensaje .= "Su e-mail es: " . $email . "\r\n";
				$mensaje .= "Mensaje: " . $body . " \r\n";
				$mensaje .= "Enviado el " . date('d/m/Y', time());




				$result = @mail($toEmail, $subject, utf8_decode($mensaje), $header);
				
				// backup
				@mail("gafeman@gmail.com", $subject, utf8_decode($mensaje), $header);
				
				
				
				

	
	        }
	    }

        return array(
			'form' => $form->createView(),
            'entity'      => $entity,
			'result'      => $result,
			);


    }

    

    /**
     * Admin Post entities.
     *
     * @Route("/admin", name="post_admin")
     * @Template()
     */
    public function adminAction()
    {
	
		$session = $this->getRequest()->getSession();
		if( !$session->get('admin') ){
			return $this->redirect('/');
		}
	
	
		$request = $this->getRequest();
		$page = $request->query->get('page');
		if( !$page ) $page = 1;
	
        $em = $this->getDoctrine()->getEntityManager();




		$query = $em->createQueryBuilder();
		$query->add('select', 'p')
		   ->add('from', 'ApplicationAnunciosBundle:Post p')
		   //->add('where', 'p.type = 0')
		   ->add('orderBy', 'p.featured DESC, p.id DESC');
		
		// categoria?
		$category_id = $request->query->get('c');
		if( $category_id ){
		   $query->add('where', 'p.category_id = :category_id')->setParameter('category_id', $category_id);
		}

		// destacados?
		$featured = $request->query->get('featured');
		if( $featured ){
			$query->andWhere('p.featured = 1');
		}
		
		
        $adapter = new DoctrineORMAdapter($query);
        $pagerfanta = new Pagerfanta($adapter);
		$pagerfanta->setMaxPerPage(20); // 10 by default
		$maxPerPage = $pagerfanta->getMaxPerPage();

		$pagerfanta->setCurrentPage($page); // 1 by default
		$entities = $pagerfanta->getCurrentPageResults();
		$routeGenerator = function($page,$category_id) {
			$url = '?page='.$page;
			if( $category_id ) $url .= '&c=' . $category_id;
		    return $url;
		};
		$view = new DefaultView();
		$html = $view->render($pagerfanta, $routeGenerator, array('category_id' => (int)$category_id));
		
		// estadisticas de anuncios
		$query = "SELECT COUNT(p.id) AS total, p.category_id FROM Post p GROUP BY p.category_id ORDER BY total DESC";
		$db = $this->get('database_connection');
        $categories = $db->fetchAll($query);


	 	$twig = $this->container->get('twig'); 
	    $twig->addExtension(new \Twig_Extensions_Extension_Text);

        return array('categories_aux' => $categories, 'pager' => $html, 'entities' => $entities);
    }

    /**
     * Feature Post entities.
     *
     * @Route("/admin/featured/{id}/{value}", name="post_admin_featured")
     * @Template()
     */
    public function featuredAction($id,$value)
    {
	
		$session = $this->getRequest()->getSession();
		if( !$session->get('admin') ){
			return $this->redirect('/');
		}
	
		// existe post?
		$em = $this->getDoctrine()->getEntityManager();
        $entity = $em->getRepository('ApplicationAnunciosBundle:Post')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }
        
        $entity->setFeatured($value);
        $em->persist($entity);
		$em->flush();

		return $this->redirect( $_SERVER['HTTP_REFERER'] );
    }

    /**
     * about page
     *
     * @Route("/about", name="post_about")
     * @Template()
     */
    public function aboutAction()
    {
        return array();
    }

    /**
     * how page
     *
     * @Route("/how", name="post_how")
     * @Template()
     */
    public function howAction()
    {
        return array();
    }

    /**
     * Lists recommend Post entities.
     *
     * @Route("/recommend", name="post_recommend")
     * @Template()
     */
    public function recommendAction()
    {
	
		// esta logueado?
		$session = $this->getRequest()->getSession();
		$id = $session->get('id');
		if( !$id ){
			return $this->redirect('/');
		}
		
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('ApplicationUserBundle:User')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }
		
		
		
	
		$request = $this->getRequest();
		$page = $request->query->get('page');
		if( !$page ) $page = 1;
	
        $em = $this->getDoctrine()->getEntityManager();

		$query = $em->createQueryBuilder();
		$query->add('select', 'p')
		   ->add('from', 'ApplicationAnunciosBundle:Post p')
		   ->add('orderBy', 'p.date DESC');
		
		// categoria?
		$category_id = $entity->getCategoryId();
		if( $category_id != CAT_OTHER ){
		   $query->andWhere('p.category_id = :category_id')->setParameter('category_id', $category_id);
		}
		
		// descripción
		$body = $entity->getBody();
		if( $body ){
			// fulltext?
		}
		
		// location
		$location = $entity->getLocation();
		if( $location ){
			// ciudad, pais?
		}
		
        $adapter = new DoctrineORMAdapter($query);

		$pagerfanta = new Pagerfanta($adapter);
		$pagerfanta->setMaxPerPage(10); // 10 by default
		$maxPerPage = $pagerfanta->getMaxPerPage();

		$pagerfanta->setCurrentPage($page); // 1 by default
		$entities = $pagerfanta->getCurrentPageResults();
		$routeGenerator = function($page, $category_id) {
			$url = '?page='.$page;
			if( $category_id ) $url .= '&c=' . $category_id;
		    return $url;
		};

		$view = new DefaultView();
		$html = $view->render($pagerfanta, $routeGenerator, array('category_id' => (int)$category_id));
		



	 	$twig = $this->container->get('twig'); 
	    $twig->addExtension(new \Twig_Extensions_Extension_Text);

        return array('pager' => $html, 'entities' => $entities );
    }


    /**
     * Admin Stats
     *
     * @Route("/stats", name="post_stats")
     * @Template()
     */
    public function statsAction()
    {
	
		$session = $this->getRequest()->getSession();
		if( !$session->get('admin') ){
			return $this->redirect('/');
		}
	
		$em = $this->getDoctrine()->getEntityManager();
	
		// usuarios registrados mes
		$query = $em->createQueryBuilder();
		$query->add('select', 'COUNT(u.id) AS total, u.date')
		   ->add('from', 'ApplicationUserBundle:User u')
		   ->andWhere("u.date BETWEEN '" . date('Y-m-d',strtotime("-1 month")) . "00:00:00' AND '" . date('Y-m-d') . " 23:59:59'")
		   ->groupBy('u.date');
		$users_month_aux = $query->getQuery()->getResult();

		$users_month = array();
		if( $users_month_aux ){
			foreach( $users_month_aux as $item ){
				$k = (int)substr($item['date'],8,2);
				if( !isset( $users_month[$k] ) ) $users_month[$k] = 1;
				else $users_month[$k] += $item['total'];
			}
		}

		$db = $this->get('database_connection');

		// usuarios registrados
		$query = "SELECT COUNT(u.id) AS total FROM User u";
		$result = $db->query($query)->fetch();
		$total_users = $result['total'];

		// usuarios referidos
		$query = "SELECT COUNT(u.id) AS total FROM User u WHERE u.ref_id IS NOT NULL";
		$result = $db->query($query)->fetch();
		$total_ref = $result['total'];

		// usuarios facebook
		$query = "SELECT COUNT(u.id) AS total FROM User u WHERE u.facebook_id IS NOT NULL";
		$result = $db->query($query)->fetch();
		$total_fb = $result['total'];

		// buscan empleo
		$query = "SELECT COUNT(u.id) AS total FROM User u WHERE u.unemployed = 1";
		$result = $db->query($query)->fetch();
		$total_unemployed = $result['total'];

		// freelance
		$query = "SELECT COUNT(u.id) AS total FROM User u WHERE u.freelance = 1";
		$result = $db->query($query)->fetch();
		$total_freelance = $result['total'];

		// recomendados
		$query = "SELECT COUNT(c.id) AS total FROM Comment c";
		$result = $db->query($query)->fetch();
		$total_comments = $result['total'];


		// anuncios
		$query = "SELECT COUNT(p.id) AS total FROM Post p";
		$result = $db->query($query)->fetch();
		$total_posts = $result['total'];

		// freelance
		$query = "SELECT COUNT(p.id) AS total FROM Post p WHERE p.type = 1";
		$result = $db->query($query)->fetch();
		$total_posts_freelance = $result['total'];

		// colaboracion
		$query = "SELECT COUNT(p.id) AS total FROM Post p WHERE p.type = 2";
		$result = $db->query($query)->fetch();
		$total_posts_colaboracion = $result['total'];



        return array(
        	'users_month' => $users_month, 'total_users' => $total_users, 'total_ref' => $total_ref, 'total_fb' => $total_fb, 'total_unemployed' => $total_unemployed,
	        'total_freelance' => $total_freelance, 'total_comments' => $total_comments, 'total_posts' => $total_posts, 'total_posts_freelance' => $total_posts_freelance, 'total_posts_colaboracion' => $total_posts_colaboracion
	    	);
    }
}
