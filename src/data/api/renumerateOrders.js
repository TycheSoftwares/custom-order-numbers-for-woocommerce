/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Triggers order renumeration via the REST API.
 *
 * @returns {Promise<Object>} Response containing total_renumerated and last_renumerated.
 */
const renumerateOrders = async () => {
    const response = await apiFetch( { path: '/con/v1/renumerate', method: 'POST' } );
    return response;
};

export default renumerateOrders;
