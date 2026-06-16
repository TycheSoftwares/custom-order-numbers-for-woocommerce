/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { __experimentalVStack as VStack } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { useCallback } from '@wordpress/element';

/**
 * External dependencies.
 */
import AsyncSelect from 'react-select/async';

/**
 * Render Token Field (it will load the woo data based on type).
 * 
 * Props:
 * @param {Object} props The properties passed to the component.
 * @param {string} props.label A text string used as the label for the input field.
 * @param {string} props.type The type of data to fetch (e.g., 'products' or a specific product category).
 * @param {Object} props.item The currently selected item(s), expected to contain an array of value objects.
 * @param {Function} props.onUpdate Callback function that updates the parent component's state with selected items.
 *
 * @returns {React.Component} Returns the FormTokenField component which allows users to select and manage tokens.
 */
function TokenField({ label, type, field, error }) {

    const { onChange, value } = field;

    const getFieldType = ( value ) => {
        switch (value) {
            case 'category':
                return 'categories';
            default:
                return value; // crude way to get field type, adjust as needed
        }
    }

    const loadOptions = useCallback(useDebounce((inputValue, callback) => {
        let path = '';
        type = getFieldType(type);
        switch (type) {
            case 'product':
                path = `/con/v1/store/products?search=${inputValue}`;
                break;
            default:
                path = `/con/v1/store/products/${type}?search=${inputValue}`;
                break;
        }

        apiFetch({ path: path })
            .then(data => {
                const options = data.map(item => (
                    {
                        label: item.title,
                        value: item.id
                    }
                ));
                callback(options);
            })
            .catch(error => {
                callback([]);
            });
    }, 300), [type]);

    return (
        <VStack>
            {label
                ? <label style={{ fontSize: '11px', fontWeight: '500', lineHeight: '1.4', textTransform: 'uppercase' }}>{label}</label>
                : ''
            }
            <AsyncSelect
                {...field}
                className={`pif-token-field ${error ? 'show_error' : ''}`}
                isMulti
                onChange={onChange}
                loadOptions={loadOptions}
                defaultValue={value}
                noOptionsMessage={() => 'Please enter at least 2 characters'}
            />
        </VStack>
    );
};

export default TokenField;
