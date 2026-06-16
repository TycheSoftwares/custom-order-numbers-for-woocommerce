/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Checks whether the rules batch processing job is still pending or running.
 *
 * @returns {Promise<{in_progress: boolean}>}
 */
const getBatchStatus = async () => {
    const response = await apiFetch({ path: '/con/v1/batch-status' });
    return response;
};

export default getBatchStatus;
