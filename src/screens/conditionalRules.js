import { useForm } from "react-hook-form";
import { 
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalHeading as Heading, 
    __experimentalText as Text, 
    Button,
    ToggleControl,
    __experimentalNumberControl as NumberControl,
    __experimentalInputControl as InputControl,
    __experimentalConfirmDialog as ConfirmDialog,
    SelectControl,
    CheckboxControl,
    Card, 
    CardHeader, 
    CardBody, 
    withNotices,
    Spinner
} from "@wordpress/components";
import { SettingsCardSection, Placeholders, OptionsTable, ConditionalValueField } from "../components";
import { useState, useEffect, useRef } from "@wordpress/element";
import { updateSettings, getBatchStatus } from "../data/api";
import { CONDITION_PLACEHOLDERS } from "../data/config";

import { __ } from '@wordpress/i18n';


function ConditionalRules( { noticeOperations, noticeUI, parentRef, settingsData, onSettingsSaved } ) {
    const toBoolean = (value) => value === 'yes' || value === true;
    const upgradeUrl = window?.conAdminData?.upgradeUrl || '#';
    const [ orderTemplate, setOrderTemplate ] = useState(settingsData?.custom_order_numbers_template || '');
    const cursorPositionRef = useRef(0);
    const [showLoader, setShowLoader] = useState(false);
    const [batchInProgress, setBatchInProgress] = useState(false);
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const showBatchNotice = () => {
        if ( document.getElementById( 'con-batch-notice' ) ) return;
        const notice = document.createElement( 'div' );
        notice.id = 'con-batch-notice';
        notice.className = 'notice notice-info';
        notice.innerHTML = `<p>${ __( 'Custom order numbers are being updated in the background.', 'custom-order-numbers-for-woocommerce' ) }</p>`;
        const appContainer = document.getElementById( 'custom-order-numbers-for-woocommerce' );
        appContainer?.parentNode?.insertBefore( notice, appContainer );
    };

    const hideBatchNotice = () => {
        document.getElementById( 'con-batch-notice' )?.remove();
    };
    const { control, handleSubmit, reset, watch, setValue, getValues, unregister, formState: { isDirty } } = useForm({
        defaultValues: settingsData ?? {
            enable_prefix_suffix: false,
            custom_order_numbers_template: orderTemplate,
            prefix_suffix_rules: []
        },
    });

    const onSubmit = async (data) => {
        await handleSettingsUpdate(data);
    };

    const onError = () => {
        noticeOperations.removeAllNotices();
        noticeOperations.createNotice({
            status: 'error',
            content: 'Error saving the settings.',
        });
    }

    useEffect(() => {
        if (settingsData) {
            reset(settingsData);
            setOrderTemplate(settingsData.custom_order_numbers_template || '{prefix}{date_prefix}{number}{suffix}{date_suffix}');
        }
    }, [settingsData, reset]);

    // If the PHP admin notice is already in the DOM on mount (batch was in progress before page load), start polling.
    useEffect(() => {
        if ( document.getElementById( 'con-batch-notice' ) ) {
            setBatchInProgress( true );
        }
    }, []);

    // Poll batch status with exponential backoff until background processing is complete.
    // Starts at 5s, doubles each poll, caps at 60s.
    useEffect(() => {
        if (!batchInProgress) return;

        let timeoutId;
        let cancelled = false;
        let delay = 5000;
        const maxDelay = 60000;

        const poll = async () => {
            try {
                const status = await getBatchStatus();
                if (cancelled) return;
                if (!status?.in_progress) {
                    setBatchInProgress(false);
                    hideBatchNotice();
                    return;
                }
            } catch {
                if (!cancelled) {
                    setBatchInProgress(false);
                    hideBatchNotice();
                }
                return;
            }
            delay = Math.min(delay * 2, maxDelay);
            timeoutId = setTimeout(poll, delay);
        };

        timeoutId = setTimeout(poll, delay);

        return () => {
            cancelled = true;
            clearTimeout(timeoutId);
        };
    }, [batchInProgress]);

    // Auto-dismiss notices after 4 seconds
    useEffect(() => {
        if (noticeUI) {
            const timer = setTimeout(() => {
                noticeOperations.removeAllNotices();
            }, 4000);

            return () => clearTimeout(timer);
        }
    }, [noticeUI]);

    const rulesDefaults = {
        enable_prefix_suffix: false,
        custom_order_numbers_template: '{prefix}{date_prefix}{number}{suffix}{date_suffix}',
        prefix_suffix_rules: [ 
            {
                condition_type: 'custom',
                condition_value: '',
                prefix: '',
                suffix: '',
                sequential: false
            },
            {
                condition_type: 'date',
                condition_value: '',
                prefix: '',
                suffix: '',
                sequential: false
            }
        ],
    };

    const resetSettings = async () => {
        setShowLoader(true);
        try {
            // Only reset rules settings — general settings are managed by the General screen
            // and preserved on the backend via merge.
            await updateSettings( rulesDefaults );
            const latestSettings = await onSettingsSaved?.();
            if ( latestSettings ) {
                reset( latestSettings );
                setOrderTemplate( latestSettings.custom_order_numbers_template || rulesDefaults.custom_order_numbers_template );
            } else {
                reset( rulesDefaults );
                setOrderTemplate( rulesDefaults.custom_order_numbers_template );
            }
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( {
                status: 'success',
                content: __( 'Rules settings have been successfully reset to default values.', 'custom-order-numbers-for-woocommerce' ),
                type: 'snackbar',
            } );
        } catch ( error ) {
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( {
                status: 'error',
                content: error.message || __( 'Error resetting the settings.', 'custom-order-numbers-for-woocommerce' ),
            } );
        } finally {
            setShowLoader( false );
            setIsDialogOpen( false );
        }
    };

    const handleTemplateChange = (value, nextCursorPosition = null) => {
        setOrderTemplate(value);
        setValue('custom_order_numbers_template', value, { shouldDirty: true });

        if (nextCursorPosition !== null) {
            cursorPositionRef.current = nextCursorPosition;
        }
    };

    const handleSettingsUpdate = async (data) => {
        setShowLoader(true);

        // Only send rules-specific fields — general settings are managed by the General screen
        // and preserved on the backend via merge.
        const { enable_prefix_suffix, custom_order_numbers_template, prefix_suffix_rules } = data;
        const rulesData = { enable_prefix_suffix, custom_order_numbers_template, prefix_suffix_rules };

        const rulesChanged = JSON.stringify(prefix_suffix_rules) !== JSON.stringify(settingsData?.prefix_suffix_rules);

        try {
            const response = await updateSettings(rulesData);

            if (!response || response.length === 0) {
                throw new Error('Unable to save settings.');
            }

            const latestSettings = await onSettingsSaved?.();
            if (latestSettings) {
                reset(latestSettings);
                setOrderTemplate(latestSettings.custom_order_numbers_template || '{prefix}{date_prefix}{number}{suffix}{date_suffix}');
            }

            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'success',
                content: 'Settings saved successfully.',
            });

            if (rulesChanged) {
                showBatchNotice();
                setBatchInProgress(true);
            }
        } catch (error) {
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'error',
                content: error.message || 'Error saving the settings.',
            });
        } finally {
            setShowLoader(false);
        }
    };

    return (
        <VStack style={{margin: '30px'}}>
            <div style={{ position: 'fixed', bottom: '20px', right: '20px', zIndex: 9999, maxWidth: '400px' }}>
                {noticeUI}
            </div>
            <form onSubmit={handleSubmit(onSubmit, onError)}>
                <VStack className={'con_setting_section'} spacing={10}>
                <SettingsCardSection
                        heading={ __( 'Order Number Template', 'custom-order-numbers-for-woocommerce' ) }
                        subHeading={ __( 'Configure the template for order numbers using available placeholders to create custom numbering formats.', 'custom-order-numbers-for-woocommerce' ) }
                        control={ control }
                        className="con_template_section"
                        fields={ [
                            {
                                name: 'custom_order_numbers_template',
                                defaultValue: '',
                                render: ( field ) => (
                                    <VStack spacing={ 1 }>
                                        <InputControl
                                            value={orderTemplate}
                                            onKeyDown={(event) => {
                                                event.stopPropagation();
                                            }}
                                            onKeyUp={(event) => {
                                                cursorPositionRef.current = event.target.selectionStart || 0;
                                            }}
                                            onClick={(event) => {
                                                cursorPositionRef.current = event.target.selectionStart || 0;
                                            }}
                                            onChange={(value, event) => {
                                                const nextCursorPosition = event?.target?.selectionStart ?? value.length;
                                                handleTemplateChange(value, nextCursorPosition);
                                                field.onChange(value);
                                            }}
                                            help={ __( 'Replaced values: {prefix}, {date_prefix}, {number}, {suffix}, {date_suffix}.', 'custom-order-numbers-for-woocommerce' ) }
                                            disabled={true}
                                        />
                                        <a href={ upgradeUrl } target="_blank" rel="noopener noreferrer" style={{ fontSize: '12px', color: '#3858e9', textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: '4px', fontWeight: 'bold' }}>
                                            { __( 'Upgrade to Pro to customize the order number template', 'custom-order-numbers-for-woocommerce' ) }
                                        </a>
                                    </VStack>
                                ),
                            }
                        ] }
                    />
                    
                    {/* <SettingsCardSection
                        heading={ __( 'Conditional Prefix/Suffix Rules', 'custom-order-numbers-for-woocommerce' ) }
                        subHeading={ __( 'Enable custom prefix and suffix rules for order numbers based on various conditions.', 'custom-order-numbers-for-woocommerce' ) }
                        control={ control }
                        fields={ [
                            {
                                name: 'enable_prefix_suffix',
                                defaultValue: false,
                                label: __( 'Enable Prefix & Suffix Rules', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <VStack spacing={ 1 }>
                                        <CheckboxControl
                                            label={ __( 'Turn on to add custom prefixes or suffixes to order numbers based on conditions you set below.', 'custom-order-numbers-for-woocommerce' ) }
                                            checked={ toBoolean(field.value) }
                                            onChange={ field.onChange }
                                            disabled={ true }
                                        />
                                        <a href={ upgradeUrl } target="_blank" rel="noopener noreferrer" style={{ fontSize: '12px', color: '#3858e9', textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: '4px', fontWeight: 'bold' }}>
                                             { __( 'Upgrade to Pro to enable conditional prefix & suffix rules', 'custom-order-numbers-for-woocommerce' ) }
                                        </a>
                                    </VStack>
                                ),
                            },
                        ] }
                    /> */}

                    <Card className="pif-field-builder">
                        <CardHeader>
                            <VStack spacing={ 2 }>
                                <Heading level={ 4 }>{ __( 'Prefix & Suffix Rules', 'custom-order-numbers-for-woocommerce' ) }</Heading>
                                    <Text className="components-text">
                                        { __( 'Set a custom or date-based prefix for your order numbers. Upgrade to Pro to unlock suffix rules and conditional rules based on product, category, payment method, and more.', 'custom-order-numbers-for-woocommerce' ) }
                                    </Text>
                            </VStack>
                        </CardHeader>
                        <CardBody>
                            
                            <OptionsTable
                                id="prefix_suffix_rules"
                                control={ control }
                                columns={ [
                                    {
                                        label: __( 'Condition Type', 'custom-order-numbers-for-woocommerce' ),
                                        tooltip: __( 'Select what the condition should be based on (e.g., product, category, user role, etc.).', 'custom-order-numbers-for-woocommerce' ),
                                    },
                                    {
                                        label: __( 'Condition Value', 'custom-order-numbers-for-woocommerce' ),
                                        tooltip: __( 'Select the specific value that matches the selected condition type (e.g., a specific product, category, etc.).', 'custom-order-numbers-for-woocommerce' ),
                                        isPro: true,
                                    },
                                    {
                                        label: __( 'Prefix', 'custom-order-numbers-for-woocommerce' ),
                                        tooltip: __( 'Set a custom prefix using static text or placeholders. For example, using {product_name} will add the full product name of the ordered item as a prefix. Available placeholders: {product_name}, {category_name}, {user_role_name}, {payment_method_name}, {country_name}.', 'custom-order-numbers-for-woocommerce' ),
                                    },
                                    {
                                        label: __( 'Suffix', 'custom-order-numbers-for-woocommerce' ),
                                        tooltip: __( 'Set a custom suffix using static text or placeholders. For example, using {product_name} will add the full product name of the ordered item as a suffix. Available placeholders: {product_name}, {category_name}, {user_role_name}, {payment_method_name}, {country_name}.', 'custom-order-numbers-for-woocommerce' ),
                                        isPro: true,
                                    },
                                    {
                                        label: __( 'Separate Counter', 'custom-order-numbers-for-woocommerce' ),
                                        tooltip: __( 'Use a separate order number counter for orders that match this rule.', 'custom-order-numbers-for-woocommerce' ),
                                        isPro: true,
                                    },
                                    {
                                        label: __( 'Action', 'custom-order-numbers-for-woocommerce' ),
                                        tooltip: __( 'Remove this rule row.', 'custom-order-numbers-for-woocommerce' ),
                                    },
                                ] }
                                templateColumns={ '1fr 1fr 1fr 1fr 0.5fr 0.5fr' }
                                optionFields={ [
                                    {
                                        name: 'condition_type',
                                        defaultValue: '',
                                        render: ( field, index ) => (
                                            <SelectControl
                                                label={''}
                                                value={field.value}
                                                options={[
                                                    { label: __('Custom', 'custom-order-numbers-for-woocommerce'), value: 'custom' },
                                                    { label: __('Date', 'custom-order-numbers-for-woocommerce'), value: 'date' },
                                                    { label: __('Product (PRO)', 'custom-order-numbers-for-woocommerce'), value: 'product', disabled: true },
                                                    { label: __('Category (PRO)', 'custom-order-numbers-for-woocommerce'), value: 'category', disabled: true },
                                                    { label: __('Payment Method (PRO)', 'custom-order-numbers-for-woocommerce'), value: 'payment_method', disabled: true },
                                                    { label: __('Country (PRO)', 'custom-order-numbers-for-woocommerce'), value: 'country', disabled: true },
                                                    { label: __('User Role (PRO)', 'custom-order-numbers-for-woocommerce'), value: 'user_role', disabled: true },
                                                    { label: __('Free Orders (PRO)', 'custom-order-numbers-for-woocommerce'), value: 'free_orders', disabled: true }
                                                ]}
                                                onChange={(value) => {
                                                    field.onChange(value);
                                                }}
                                                __nextHasNoMarginBottom
                                            />
                                        ),
                                    },
                                    {
                                        name: 'condition_value',
                                        defaultValue: '',
                                        render: ( field, index ) => {
                                            return (
                                                <Text style={{fontStyle: 'italic'}}>{ __( '(no condition value needed)', 'custom-order-numbers-for-woocommerce')}</Text>
                                            );
                                        },
                                    },
                                    {
                                        name: 'prefix',
                                        defaultValue: '',
                                        render: ( field, index ) => {
                                            const conditionType = watch( `prefix_suffix_rules.${ index }.condition_type` ) || 'custom';
                                            const placeholders = CONDITION_PLACEHOLDERS[ conditionType ] || [];
                                            return (
                                                <InputControl
                                                    value={ field.value }
                                                    onChange={ field.onChange }
                                                    placeholder={ placeholders.join( ', ' ) }
                                                />
                                            );
                                        },
                                    },
                                    {
                                        name: 'suffix',
                                        defaultValue: '',
                                        render: ( field, index ) => {
                                            const conditionType = watch( `prefix_suffix_rules.${ index }.condition_type` ) || 'custom';
                                            const placeholders = CONDITION_PLACEHOLDERS[ conditionType ] || [];
                                            return (
                                                <InputControl
                                                    value={ field.value }
                                                    onChange={ field.onChange }
                                                    placeholder={ placeholders.join( ', ' ) }
                                                    disabled={ true }
                                                />
                                            );
                                        },
                                    },
                                    {
                                        name: 'sequential',
                                        defaultValue: '',
                                        render: ( field ) => (
                                            <CheckboxControl
                                                checked={ toBoolean( field.value ) }
                                                onChange={ field.onChange }
                                                disabled={ true }
                                            />
                                        ),
                                    },
                                ] }
                                defaultValue={ [
                                    { condition_type: 'custom', condition_value: '', prefix: '', suffix: '', sequential: false },
                                    { condition_type: 'date', condition_value: '', prefix: '', suffix: '', sequential: false }
                                ] }
                            />
                        </CardBody>
                    </Card>

                    <div>
                        <HStack spacing={3} expanded={false} justify="left">
                            <Button variant="primary" type="submit">{ __( 'Save Changes', 'custom-order-numbers-for-woocommerce' ) }</Button>
                            <Button
                                variant="secondary"
                                onClick={ () => setIsDialogOpen( true ) }
                            >
                                { __( 'Reset Settings', 'custom-order-numbers-for-woocommerce' ) }
                            </Button>
                        </HStack>
                        <ConfirmDialog
                            isOpen={ isDialogOpen }
                            cancelButtonText={ __( 'Cancel', 'custom-order-numbers-for-woocommerce' ) }
                            confirmButtonText={ __( 'Reset', 'custom-order-numbers-for-woocommerce' ) }
                            onCancel={ () => setIsDialogOpen( false ) }
                            onConfirm={ resetSettings }
                        >
                            { __( 'Are you sure you want to reset all rules settings to their default values? This will clear all prefix/suffix rules and reset the order number template.', 'custom-order-numbers-for-woocommerce' ) }
                        </ConfirmDialog>
                    </div>

                </VStack>
            </form>

            <style>
                    {`
                    tr:not(:last-child) td{
                        padding-bottom: 30px;
                    }
                    td:nth-child(2){
                        padding-left: 30px;
                    }
               `}
            </style>

            {showLoader ? <div className="con_loader">< Spinner style={{ width: '30px', height: '30px' }
            } /></div > : ''}
        </VStack>
    );
}

export default withNotices(ConditionalRules);