<?php

namespace moove\ActiviteBundle\Entity;

use Doctrine\ORM\EntityRepository;


/**
 * ActiviteRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ActiviteRepository extends EntityRepository
{
    // Requête type tous élément inclus (sauf date): 
    // http://moove-arl64.c9users.io/web/app_dev.php/rechercher?niveau=[intermediaire,expert]&sport=[Jogging,Cyclisme,Ski]&placeRestanteMax=8&placeRestanteMin=3&nbPlace=15&photo=yes&order=sport&type=asc&page=1
    // http://moove-arl64.c9users.io/web/app_dev.php/rechercher?date=2016-04-14&hMin=13h00&hMax=15h00&niveau=[intermediaire,expert]&sport=[Jogging,Cyclisme,Ski]&placeRestanteMax=8&placeRestanteMin=3&nbPlace=15&photo=yes&order=sport&type=asc&page=1
    
    /**
     * remplace find
     * @param $idActivite <i>int</i> id de l'activité rechercher
     * @return <i>Activite</i> activité demandé, plus tous les détails liée.
     */
    public function findWhitDetail($idActivite)
    {
        $requete = $this->_em->createQueryBuilder()
                    ->select('a')
                    ->from($this->_entityName, 'a')
                    ->join('a.organisateur', 'u')
                    ->addSelect('u')
                    ->join('a.sportPratique', 's')
                    ->addSelect('s')
                    ->join('a.niveauRequis', 'n')
                    ->addSelect('n')
                    ->join('a.lieuRDV', 'lrdv')
                    ->addSelect('lrdv')
                    ->leftJoin('a.lieuDepart', 'ld')
                    ->addSelect('ld')
                    ->leftJoin('a.lieuArrivee', 'la')
                    ->addSelect('la')
                    ->where('a.id = :idActivite')
                    ->setParameter('idActivite', $idActivite)
        ;
        
        $query = $requete->getQuery();

        return $query->getSingleResult();
    }
    
    /**
     * retourne une liste complète de toute les activités répondant au nombreux critère
     * 
     * @param $idUtilisateur <i>Utilisateur</i> utilisateur concerné
     * @param $estAccepter <i>Integer</i> etat de sa demande (0:en cours, 1:accepter, 2:refuser)
     * @param $terminer <i>boolean</i>=null etat de l'activité (true:fini, false:en cours)
     * 
     * @param $datePrecise <i>String</i> format YYYY-mm-dd
     * @param $heureMin <i>String</i> format 00h00. Nécessire une date Précise pour fonctionné
     * @param $heureMax <i>String</i> format 00h00. Nécessire une date Précise pour fonctionné
     * @param $sport <i>String</i> format [Nom1,Nom2]. substitut de tupe array contenant la liste des sports concerné
     * @param $niveau <i>String</i> format [Nom1,Nom2]. substitut de tupe array contenant la liste des niveaux concerné
     * @param $photo <i>String</i> constante 'yes' ou 'no' indiquant la précense ou non de la photo. 
     * @param $nbPlaceRestante <i>String</i> valeur sous forme de string indiquant le nombre de place restante minimum.
     * @param $nbPlaceMin <i>String</i> valeur sous forme de string indiquant le nombre de place minimum
     * @param $nbPlaceMax <i>String</i> valeur sous forme de string indiquant le nombre de place maximum
     * @param $distanceMax <i>String</i> valeur
     * @param $order <i>String</i>="a.dateHeureRDV" schema de la base de donnée correspondente pour le 'orderBy'
     * @param $type <i>String</i>="DESC" constante correspondante pour l'ordre de try (ASC ou DESC)
     * @return <i>Array<Activite></i> liste de toute les activité correspondante
     */
    public function findWhitCondition($datePrecise, $heureMin, $heureMax, $sport, $niveau, $photo, $nbPlaceRestante, $nbPlaceMin, $nbPlaceMax, $distanceMax, $order = "a.dateHeureRDV", $type = "ASC")
    {
        // On créer notre grosse requete de base
        $requete = $this->_em->createQueryBuilder()
                    ->select('a')
                    ->from($this->_entityName, 'a')
                    //->leftJoin('mooveActiviteBundle:Participer', 'p', 'WITH', 'a.id = p.activite')
                    ->join('a.participer', 'p')
                    ->join('a.organisateur', 'u')
                    ->addSelect('u')
                    ->join('a.sportPratique', 's')
                    ->addSelect('s')
                    ->join('a.niveauRequis', 'n')
                    ->addSelect('n')
                    ->join('a.lieuRDV', 'lrdv')
                    ->addSelect('lrdv')
                    ->leftJoin('a.lieuDepart', 'ld')
                    ->addSelect('ld')
                    ->leftJoin('a.lieuArrivee', 'la')
                    ->addSelect('la')
                    ->andWhere('a.estTerminee = 0')
                    ->andWhere('p.estAccepte = 1')
                    //->andWhere('p.estAccepte <> 1')
                    ->orderBy($order, $type)
        ;

        // puis on ajoute chaque condition. On suppose que si la valeur est null, alors elle n'est pas utile. On adapte ainsi les conditions.
        
        // si la date n'est pas renseigner, on ne peu pas définir les heures minimum et maximum
        // date=2016-04-14&
        if(!is_null($datePrecise))
        {
            // Si le champ heure minimum existe, alors on l'extrais et on l'ajoute à notre string indiquant la date
            // hMin=13h00&
            if(!is_null($heureMin))
            {
                $temps = explode('h', $heureMin);
                $time = $datePrecise . " " .$temps[0] . ':' . $temps[1] . ':00';
            }
            else // Si il n'existe pas, on ajoute comme date minimum, la premier seconde de la journée ( sa savoir minuit pile)
            {
                $time = $datePrecise . " 00:00:00";
            }
            // On créer notre objet Datetime une fois que le string est complet.
            $timeHeureMin = new \Datetime($time);

            // On ré-itère la fonction pour l'heure max
            // hMax=15h00&
            if(!is_null($heureMax))
            {
                $temps = explode('h', $heureMax);
                $time = $datePrecise . " " .$temps[0] . ':' . $temps[1] . ':00';
            }
            else 
            {
                // en revanche, on utilise la dernier seconde de la journée 
                $time = $datePrecise . " 23:59:59";
            }
            $timeHeureMax = new \Datetime($time);

            // On ajoute ensuite notre condition BETWEEN a notre query actuel, tous en indiquant le format des dates.
            $requete    ->andWhere('a.dateHeureRDV BETWEEN :heureMin AND :heureMax')
                        ->setParameter('heureMin', $timeHeureMin->format('Y-m-d H:i:s'))
                        ->setParameter('heureMax', $timeHeureMax->format('Y-m-d H:i:s'))
            ;
        }   
        
        // Sport et Niveau sont des arrays. On créer donc notre array a partir de la syntaxe prédéfinis (a savoir "[val1,val2,val3]") puis on utile la fonction "IN"
        if(!is_null($sport))
        { // sport=[Jogging,Ski]&
            $tabSport = explode(',', substr($sport, 1, strlen($sport)-2));
            $requete->andWhere('s.nom IN (:tabSport)')
                    ->setParameter('tabSport', $tabSport);
            ;
        }
    
        if(!is_null($niveau))
        { // niveau=[debutant,expert]&
            $tabNiveau = explode(',', substr($niveau, 1, strlen($niveau)-2));
            $requete->andWhere('n.libelle IN (:tabNiveau)')
                    ->setParameter('tabNiveau', $tabNiveau);
            ;
        }
        
        // On gère ici un boolean. On a définis le mot clé "yes" (eventuellement changable). Si le boolean n'est pas a yes, mais qu'il est définis, on l'ignore simplement. Le cas échéant, on vérifie que la valeur soit différente de la valeur par défaut.
        if(!is_null($photo))
        { // photo=yes&
            if(!strcmp($photo, "yes"))
            {
                $requete->andWhere("u.URLAvatar <> 'default.png'");
            }
            else if(!strcmp($photo, "no"))
            {
                $requete->andWhere("u.URLAvatar = 'default.png'");
            }
        }
    
        // Ici, on se contente d'ajouter la condition si la variable n'est pas null.
        if(!is_null($nbPlaceRestante))
        { // nbPlace=5&
            
            $requete->addGroupBy('a.id')
                    ->andHaving("COUNT(p.id) >= :nbPlaceRestante")
                    ->setParameter('nbPlaceRestante', $nbPlaceRestante);
            ;
        }

        // GroupBy permet de regrouper les ligne par id d'activité. Ainsi, grace au "Having" on peu compté le nombre de participant a l'activité. On se contente ensuite de rajouté la condition adéquate.
        if(!is_null($nbPlaceMin))
        { 
            $requete->andWhere('a.nbPlaces >= :nbPlaceMin')
                    ->setParameter('nbPlaceMin', $nbPlaceMin);
            ;
        }
      
        if(!is_null($nbPlaceMax))
        { 
            $requete->andWhere('a.nbPlaces <= :nbPlaceMax')
                    ->setParameter('nbPlaceMax', $nbPlaceMax);
            ;
        }
        
          
        // on récupère la commande DQL
        $query = $requete->getQuery();
        //var_dump($query->getResult());

        // on retourne un tableau de résultat
        return $query->getResult();
    }
    
    /**
     * retourne une liste complète de toute les activités dont l'état de la participation pour l'utilisateur conerné correspond a l'état passer en paramètre
     * 
     * @param $idUtilisateur <i>Utilisateur</i> utilisateur concerné
     * @param $estAccepter <i>Integer</i> etat de sa demande (0:en cours, 1:accepter, 2:refuser)
     * @param $terminer <i>boolean</i>=null etat de l'activité (true:fini, false:en cours)
     * @param $order <i>String</i>="a.dateHeureRDV" schema de la base de donnée correspondente pour le 'orderBy'
     * @param $type <i>String</i>="DESC" constante correspondante pour l'ordre de try (ASC ou DESC)
     * @return <i>Array<Activite></i> liste de toute les activité correspondante
     */
    public function findByUtilisateurAccepter($idUtilisateur, $estAccepter, $terminer = null, $order = "a.dateHeureRDV", $type = "DESC")
    {
        $requete = $this->getAllActivityForUser($idUtilisateur, $order, $type)
                        ->andWhere('p.estAccepte = :estAccepte')
                        ->setParameter('estAccepte', $estAccepter)
                    ;
        if(!is_null($terminer))
        {
            $requete->andWhere('a.estTerminee = :fini')
                    ->setParameter('fini', $terminer)
            ;
        }
        
        
        // on récupère la commande DQL
        $query = $requete->getQuery();
        
        // on retourne un tableau de résultat
        return $query->getResult();
    }
    
    /**
     * retourne la liste de toute les activités auquel participe l'utilisateur.
     * 
     * @param $idUtilisateur <i>Utilisateur</i> utilisateur concerné
     * @param $terminer <i>boolean</i>=null etat de l'activité (true:fini, false:en cours)
     * @param $order <i>String</i>="a.dateHeureRDV" schema de la base de donnée correspondente pour le 'orderBy'
     * @param $type <i>String</i>="DESC" constante correspondante pour l'ordre de try (ASC ou DESC)
     * @return <i>Array<Activite></i> liste de toute les activité correspondante
     */
    public function findByUtilisateur($idUtilisateur, $terminer = null, $order = "a.dateHeureRDV", $type = "DESC")
    {
        // on récupère la query de base de séléction des activités par utilisateur
        $requete = $this->getAllActivityForUser($idUtilisateur, $order, $type);
        
        // on ajoute la condition terminer ou non
        if(!is_null($terminer))
        {
            $requete->andWhere('a.estTerminee = :fini')
                    ->setParameter('fini', $terminer)
            ;
        }
        
        // on récupère la commande DQL
        $query = $requete->getQuery();
        
        // on retourne un tableau de résultat
        return $query->getResult();
    }


    /**
     * 
     * @param $idActivite <i>Activite</i> activite concerné
     * @param $organisateur <i>Utilisateur</i> 
     * @return <i>Array<></i> 
     */ 
    public function supprimerActivite($idActivite, $organisateur)
    {
         $requete1 = $this->createQueryBuilder('p')
                        ->delete('mooveActiviteBundle:Participer', 'p')
                        ->where('p.activite = :activite')
                        ->setParameter('activite', $idActivite)
                        ;
                        
        $requete2 = $this->_em->createQueryBuilder()
            ->delete($this->_entityName, 'a')
            ->where('a.organisateur = :organisateur')
            ->setParameter('organisateur', $organisateur)
            ->andWhere('a.id = :activite')
            ->setParameter('activite', $idActivite)
            ;
                        
         // on récupère la commande DQL
        $query1 = $requete1->getQuery();
        $query2 = $requete2->getQuery();
        
        // on retourne un tableau de résultat
        return array($query1->getResult(), $query2->getResult());  
    }
    
    /**
     * 
     * @param $idUtilisateur <i>Utilisateur</i> utilisateur concerné
     * @param $order <i>String</i> schema de la base de donnée correspondente pour le 'orderBy'
     * @param $type <i>String</i> constante correspondante pour l'ordre de try (ASC ou DESC)
     * @return <i>queryBuilder</i> base de toute querry souhaitant faire apelle a une activité, et ses détails
     */
    protected function getAllActivityForUser($idUtilisateur, $order, $type)
    {
        // création de la requete de base
        $requete = $this->_em->createQueryBuilder()
            ->select('a')
            ->from($this->_entityName, 'a')
            ->leftJoin('mooveActiviteBundle:Participer', 'p', 'WITH', 'a.id = p.activite')
            ->join('a.organisateur', 'u')
            ->addSelect('u')
            ->join('a.sportPratique', 's')
            ->addSelect('s')
            ->join('a.lieuRDV', 'lrdv')
            ->addSelect('lrdv')
            ->leftJoin('a.lieuDepart', 'ld')
            ->addSelect('ld')
            ->leftJoin('a.lieuArrivee', 'la')
            ->addSelect('la')
            
            ->where('p.utilisateur = :idUtilisateur')
            
            ->orderBy($order, $type)
            //->orderBy('a.dateHeureRDV', 'DESC')
            ->setParameter('idUtilisateur', $idUtilisateur)
        ;
        return $requete;
    }
}
