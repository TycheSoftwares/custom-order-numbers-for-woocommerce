import { 
    ToggleControl,
    __experimentalNumberControl as NumberControl,
    __experimentalInputControl as InputControl,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalText as Text, 
    SelectControl,
    FormTokenField,
    DatePicker,
    Dropdown,
    Button,
    TimePicker,
} from "@wordpress/components";
import { __ } from '@wordpress/i18n';

import { dateI18n, format, getSettings as getDateSettings } from '@wordpress/date';

import { TokenField } from ".";

/**
 * Converts a JavaScript Date object to WordPress timezone string.
 * 
 * @param {Date} date - The date to convert.
 * @return {string} - The formatted date string.
 */
const WpTimezone = (date) => {
    return dateI18n('Y-m-d', date, getDateSettings().timezone.offset);
};

const wpTimezoneTime = (date) => {
    return dateI18n('H:i', date, getDateSettings().timezone.offset);
}

/**
 * Functional component to render a date picker control.
 * 
 * @param {object} props - Component properties including label, control object, and field name.
 */
const DatePickerControl = ({ field }) => {
    return (
        <Dropdown
            popoverProps={{ placement: 'bottom-start' }}
            renderToggle={({ isOpen, onToggle }) => (
                <InputControl
                    value={field.value ? format(getDateSettings().formats.date, field.value) : ''}
                    onChange={field.onChange}
                    aria-expanded={isOpen}
                    onClick={onToggle}
                />
            )}
            renderContent={() => (
                <VStack>
                    <HStack spacing="2" justify="right">
                        <Button variant="primary" onClick={() => field.onChange(WpTimezone(new Date()))}>Today</Button>
                        <Button onClick={() => field.onChange('')}>Clear</Button>
                    </HStack>
                    <DatePicker
                        currentDate={field.value ? field.value : WpTimezone(new Date())}
                        onChange={field.onChange}
                    />
                </VStack>
            )}
        />
    );
};

const ConditionalValueField = ({ field, fieldType }) => {
    // fieldType comes from your field's actual type (text, number, select, etc.)
    // conditionType comes from type_conditional_options_condition watch value

    // Products / Categories / Tags / Variations → TokenField
    if ([
        'product',
        'category',
    ].includes(fieldType)) {
        return <TokenField type={fieldType} field={field} />;
    }

    // Country → WC countries dropdown
    if (fieldType === 'country') {
        const wcCountries = wcSettings.countries || {};
        const countryOptions = Object.entries(wcCountries).map(([code, name]) => ({
            value: code,
            label: name
        }));
        const validOptionValues = countryOptions.map(o => o.value);

        const cleanedValues = (field.value || []).filter(v =>
            validOptionValues.includes(v.value)
        );
    
        if (cleanedValues.length !== (field.value || []).length) {
            field.onChange(cleanedValues);
        }
        
        return (
            <FormTokenField
                label=''
                className="con-token-field"
                value={cleanedValues.map(v => v.label)}
                suggestions={countryOptions.map(c => c.label)}
                onChange={(tokens) => {
                    const selected = tokens
                        .map(token =>
                            countryOptions.find(c => c.label === token)
                        )
                        .filter(Boolean);
            
                    field.onChange(selected);
                }}
                placeholder='Search for a country...'
            />
        );
    }

    if ( [ 'payment_method', 'user_role' ].includes( fieldType ) ) {
        const localizedOptions = {
            payment_method: window?.conAdminData?.paymentGateways || [],
            user_role: window?.conAdminData?.userRoles || [],
        };

        const options = localizedOptions[ fieldType ] || [];
        const validOptionValues = options.map( ( option ) => option.value );

        const cleanedValues = ( field.value || [] ).filter( ( selectedValue ) =>
            validOptionValues.includes( selectedValue.value )
        );

        if ( cleanedValues.length !== ( field.value || [] ).length ) {
            field.onChange( cleanedValues );
        }

        return (
            <FormTokenField
                label=''
                className="con-token-field"
                value={cleanedValues.map((selectedValue) => selectedValue.label)}
                suggestions={options.map((option) => option.label)}
                onChange={(tokens) => {
                    const selected = tokens
                        .map((token) => options.find((option) => option.label === token))
                        .filter(Boolean);

                    field.onChange(selected);
                }}
                __experimentalShowHowTo={false}
                placeholder={ fieldType === 'payment_method' ? 'Search for a payment method...' : 'Search for a user role...' }
            />
        );
    }


    // (default)
    return <Text style={{fontStyle: 'italic'}}>{ __( '(no condition value needed)', 'custom-order-numbers-for-woocommerce')}</Text>;
};

export default ConditionalValueField;