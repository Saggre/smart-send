<?php

namespace Smartsend;

require_once 'Client.php';
require_once 'Models/Agent.php';
require_once 'Models/Shipment.php';

use Smartsend\Models\Agent;
use Smartsend\Models\Shipment;

class Api extends Client
{
    // Agent API

    public function getAgent($id)
    {
        return $this->httpGet('agents/'.$id);
    }

    public function getAgentByAgentNo($carrier, $agent_no)
    {
        return $this->httpGet('agents/carrier/'.$carrier.'/agentno/'.$agent_no);
    }

    public function findFirstAgent($criteria)
    {
        // TODO: Implement function to search for $criteria
        return $this->httpGet('agents/123');
    }

    public function updateAgent($id, Agent $agent)
    {
        return $this->httpPut('agents/'.$id, array(), array(), $agent);
    }

    public function deleteAgent($id)
    {
        return $this->httpDelete('agents/'.$id);
    }

    public function createAgent(Agent $agent)
    {
        return $this->httpPost('agents/', array(), array(),$agent);
    }

    public function getAgentsByCountry($carrier, $country)
    {
        return $this->httpGet('agents/carrier/'.$carrier.'/country/'.$country);
    }

    /*
     * Find  agents located in postal code (exact match)
     *
     * @param string $carrier is the carrier for which to find agents
     * @param string $country is the country in which the agents should be located
     * @param string $postal_code is the postal code to search for close agents from
     *
     * return TODO: Add return explenation
     * return TODO: Add explenation about exceptions
     */
    public function getAgentsByPostalCode($carrier, $country, $postal_code)
    {
        return $this->httpGet('agents/carrier/'.$carrier.'/country/'.$country.'/postalcode/'.$postal_code);
    }

    /*
     * Find  agents located on street (exact match)
     *
     * @param string $carrier is the carrier for which to find agents
     * @param string $country is the country in which the agents should be located
     * @param string $postal_code is the postal code which the agents should be located
     * @param string $street is the street name on which the agents should be located
     *
     * return TODO: Add return explenation
     * return TODO: Add explenation about exceptions
     */
    public function getAgentsByAddress($carrier, $country, $postal_code, $street)
    {
        return $this->httpGet('agents/carrier/'.$carrier.'/country/'.$country.'/postalcode/'.$postal_code.'/street/'.$street);
    }
    
    /*
     * Get agents located within an area
     *
     * @param string $carrier is the carrier for which to find agents
     * @param string $country optionally country in which the agents should be located
     * @param string $min_latitude Agents will be located an a latitude larger than this value
     * @param string $max_latitude Agents will be located an a latitude lower than this value
     * @param string $min_longitude Agents will be located an a longitude larger than this value
     * @param string $max_longitude Agents will be located an a longitude lower than this value
     *
     * return TODO: Add return explenation
     * return TODO: Add explenation about exceptions
     */
    public function getAgentsInArea($carrier, $country=null, $min_latitude, $max_latitude,$min_longitude, $max_longitude)
    {
    	if($country) {
        	return $this->httpGet('agents/carrier/'.$carrier.'/country/'.$country
        	.'/area/latitude/min/'.$min_latitude.'/max/'.$max_latitude
        	.'/longitude/min/'.$min_longitude.'/max'.$max_longitude);
        } else {
        	return $this->httpGet('agents/carrier/'.$carrier
        	.'/area/latitude/min/'.$min_latitude.'/max/'.$max_latitude
        	.'/longitude/min/'.$min_longitude.'/max'.$max_longitude);
        }
    }

    /*
     * Find closest agents by postal code (not necessarily with exact match)
     *
     * @param string $carrier is the carrier for which to find agents
     * @param string $country is the country in which the agents should be located
     * @param string $postal_code is the postal code to search for close agents from
     *
     * return TODO: Add return explenation
     * return TODO: Add explenation about exceptions
     */
    public function findClosestAgentByPostalCode($carrier, $country, $postal_code)
    {
        return $this->httpGet('agents/closest/carrier/'.$carrier.'/country/'.$country.'/postalcode/'.$postal_code);
    }

    /*
     * Find closest agents by address (not necessarily with exact match)
     *
     * @param string $carrier is the carrier for which to find agents
     * @param string $country is the country in which the agents should be located
     * @param string $postal_code is the postal code to search for close agents from
     * @param string $street is the street name to search for close agents from
     *
     * return TODO: Add return explenation
     * return TODO: Add explenation about exceptions
     */
    public function findClosestAgentByAddress($carrier, $country, $postal_code, $street)
    {
        return $this->httpGet('agents/closest/carrier/'.$carrier.'/country/'.$country.'/postalcode/'.$postal_code.'/street/'.$street);
    }

    /*
     * Find closest agents by GPS coordinates
     *
     * @param string $carrier is the carrier for which to find agents
     * @param string $country is the country in which the agents should be located
     * @param string $latitude is the latitude of the GPS coordinates to search for close agents from
     * @param string $longitude is the longitude of the GPS coordinates to search for close agents from
     *
     * return TODO: Add return explenation
     * return TODO: Add explenation about exceptions
     */
    public function findClosestAgentByGpsCoordinates($carrier, $country, $latitude, $longitude)
    {
        return $this->httpGet('agents/closest/carrier/'.$carrier.'/country/'.$country.'/coordinates/latitude/'.$latitude.'/longitude/'.$longitude);
    }

// Shipment API

    public function getShipment($id)
    {
        return $this->httpGet('shipments/'.$id);
    }

    public function findShipments()
    {
        return $this->httpGet('shipments');
    }

    public function createShipment(Shipment $shipment)
    {
        return $this->httpPost('shipments', array(), array(), $shipment);
    }

    public function updateShipment($id, Shipment $shipment)
    {
        return $this->httpPut('shipments'.$id, array(), array(), $shipment);
    }

    public function deleteShipment($id)
    {
        return $this->httpDelete('shipments/'.$id);
    }

// Label API
    public function getLabels($shipment_id, $parcel_id=null)
    {
        if($parcel_id) {
            return $this->httpGet('shipments/'.$shipment_id.'/parcels/'.$parcel_id.'/label');
        } else {
            return $this->httpGet('shipments/'.$shipment_id.'/labels');
        }
    }

    public function getPdfLabels($shipment_id, $parcel_id=null)
    {
        if($parcel_id) {
            return $this->httpGet('shipments/'.$shipment_id.'/parcels/'.$parcel_id.'/label/pdf');
        } else {
            return $this->httpGet('shipments/'.$shipment_id.'/labels/pdf');
        }
    }

    public function findLabel()
    {
        return $this->httpGet('shipments');
    }

    public function createLabels($shipment_id)
    {
        return $this->httpPost('shipments/'.$shipment_id.'/labels');
    }

    public function createShipmentAndLabels($shipment)
    {
        return $this->httpPost('shipments/labels',array(),array(),$shipment);
    }


// General part


    /**
     * Does API response contain link to next page of results
     * @return  boolean
     */
    public function hasNextLink()
    {
        // Todo: Fix problem: the link is private
        return false;
        //return !empty($this->links->next);
    }

    /**
     * Does API response contain link to previous page of results
     * @return  boolean
     */
    public function hasPreviousLink()
    {
        // Todo: Fix problem: the link is private
        return false;
        //return !empty($this->links->previous);
    }

    /**
     * Does API response contain link to last page of results
     * @return  boolean
     */
    public function hasLastLink()
    {
        // Todo: Fix problem: the link is private
        return false;
        //return !empty($this->links->last);
    }

    /**
     * Does API response contain link to next page of results
     * @return  boolean
     * @throws NotFoundException
     */
    public function next()
    {
        if($this->hasNextLink()) {
            // TODO: load next
        } else {
            throw new NotFoundException('There are no next page');
        }
    }

    /**
     * Does API response contain link to next page of results
     * @return  boolean
     * @throws NotFoundException
     */
    public function previous()
    {
        if($this->hasPreviousLink()) {
            // TODO: load previous
        } else {
            throw new NotFoundException('There are no previous page');
        }
    }

    /**
     * Does API response contain link to next page of results
     * @return  boolean
     * @throws NotFoundException
     */
    public function last()
    {
        if($this->hasLastLink()) {
            // TODO: load last
        } else {
            throw new NotFoundException('There are no last page');
        }
    }
}