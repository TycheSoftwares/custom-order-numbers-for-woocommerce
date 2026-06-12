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
    RadioControl,
    CheckboxControl,
    DatePicker,
    withNotices,
    Spinner,
    Snackbar,
    FormTokenField,
} from "@wordpress/components";

import { __ } from '@wordpress/i18n';
import { SettingsCardSection } from "../components";
import { useState, useEffect, useRef } from "@wordpress/element";
import { updateSettings, resetTracking as resetTrackingApi, getBatchStatus } from "../data/api";

function General({ noticeOperations, noticeUI, parentRef, settingsData, onSettingsSaved }) {
    const noticesStore = window?.wp?.data?.dispatch?.("core/notices");
    const isWcVariant = !! window?.conAdminData?.isWcVariant;
    const toBoolean = (value) => value === 'yes' || value === true;
    const [showLoader, setShowLoader] = useState(false);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [batchInProgress, setBatchInProgress] = useState(false);

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
    const [isActionsSticky, setIsActionsSticky] = useState(false);
    const actionsRef = useRef(null);
    const scrollDirectionRef = useRef('down');
    const lastScrollYRef = useRef(0);

    const { control, handleSubmit, reset, watch, setValue, getValues, unregister, formState: { isDirty } } = useForm({
        defaultValues: settingsData ?? {
            enabled: false,
            counter_type: 'sequential',
            counter: 1,
            counter_reset_enabled: 'no',
            day_of_counter_reset_weekly: 'mon',
            counter_reset_counter_value: 1,
            min_width: 1,
            settings_to_apply: 'new_order',
            settings_to_apply_from_order_id: 0,
            settings_to_apply_from_date: '',
            order_tracking_enabled: false,
            manual_enabled: false,
            hide_menu_for_roles: [],
            hide_tab_for_roles: [],
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

    const showNotice = ( message, status ) => {

        const noticesStore =
            window?.wp?.data?.dispatch?.("core/notices");

        // ✅ Try WP notices first
        if (noticesStore && noticesStore.createNotice) {

            noticesStore.createNotice(
                status,
                message,
                { isDismissible: true }
            );

            return;
        }

    };

    const handleSettingsUpdate = async (data) => {
        setShowLoader(true);

        // Strip rules-specific fields — those are managed by the Prefix/Suffix Rules screen.
        const { enable_prefix_suffix, custom_order_numbers_template, prefix_suffix_rules, ...generalData } = data;

        const prevSettingsToApply = settingsData?.settings_to_apply;
        const prevFromOrderId     = settingsData?.settings_to_apply_from_order_id;
        const prevFromDate        = settingsData?.settings_to_apply_from_date;

        const applySettingsChanged = (
            generalData.settings_to_apply !== prevSettingsToApply ||
            generalData.settings_to_apply_from_order_id !== prevFromOrderId ||
            generalData.settings_to_apply_from_date !== prevFromDate
        );

        const willTriggerBatch = applySettingsChanged &&
            ( generalData.settings_to_apply === 'order_id' || generalData.settings_to_apply === 'date' );

        try {
            const response = await updateSettings(generalData);

            if (!response || response.length === 0) {
                throw new Error('Unable to save settings.');
            }

            const latestSettings = await onSettingsSaved?.();
            if (latestSettings) {
                reset(latestSettings);
            }

            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'success',
                content: 'Settings saved successfully.',
                type: 'snackbar',
            });

            if (willTriggerBatch) {
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

    const defaultSettings = {
        enabled: false,
        counter_type: 'sequential',
        counter: 1,
        counter_reset_enabled: 'no',
        day_of_counter_reset_weekly: 'mon',
        counter_reset_counter_value: 1,
        min_width: 1,
        settings_to_apply: 'new_order',
        settings_to_apply_from_order_id: 0,
        settings_to_apply_from_date: '',
        order_tracking_enabled: false,
        manual_enabled: false,
        hide_menu_for_roles: [],
        hide_tab_for_roles: [],
    };

    const resetSettings = async () => {
        setShowLoader(true);
        try {
            // Only reset general settings — rules fields are managed by the Prefix/Suffix Rules screen
            // and preserved on the backend via merge.
            await updateSettings( defaultSettings );
            const latestSettings = await onSettingsSaved?.();
            reset( latestSettings ?? defaultSettings );
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'success',
                content: __( 'Settings have been successfully reset to default values.', 'custom-order-numbers-for-woocommerce' ),
                type: 'snackbar',
            });
        } catch ( error ) {
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'error',
                content: error.message || __( 'Error resetting the settings.', 'custom-order-numbers-for-woocommerce' ),
            });
        } finally {
            setShowLoader(false);
            setIsDialogOpen(false);
        }
    }

    const resetTracking = async () => {
        setShowLoader(true);
        try {
            await resetTrackingApi();
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'success',
                content: __( 'Tracking has been successfully reset.', 'custom-order-numbers-for-woocommerce' ),
                type: 'snackbar',
            });
        } catch ( error ) {
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'error',
                content: error.message || __( 'Error resetting tracking.', 'custom-order-numbers-for-woocommerce' ),
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
                        heading={ __( 'Custom Order Numbers Options', 'custom-order-numbers-for-woocommerce' ) }
                        subHeading={ __( 'Enable sequential order numbering, set custom number prefix, suffix and number width.', 'custom-order-numbers-for-woocommerce') }
                        control={ control }
                        fields={ [
                            {
                                name: 'enabled',
                                defaultValue: false,
                                label: __( 'WooCommerce Custom Order Numbers', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <CheckboxControl
                                        label={ __( 'Enable Custom Order Numbers', 'custom-order-numbers-for-woocommerce' ) }
                                        help={ __( 'Turn this on to start using custom order numbering for your store.', 'custom-order-numbers-for-woocommerce' ) }
                                        checked={ toBoolean(field.value) }
                                        onChange={ field.onChange }
                                    />
                                ),
                            },
                            {
                                name: 'counter_type',
                                defaultValue: 'sequential',
                                label: __( 'Order Numbers Counter', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <SelectControl
                                            label={''}
                                            value={field.value}
                                            options={[
                                                { label: __('Sequential', 'custom-order-numbers-for-woocommerce'), value: 'sequential' },
                                                { label: __('Order ID', 'custom-order-numbers-for-woocommerce'), value: 'order_id' },
                                                { label: __('Pseudorandom - crc32 Hash (max 10 digits)', 'custom-order-numbers-for-woocommerce'), value: 'hash_crc32' },
                                            ]}
                                            onChange={(value) => {
                                                field.onChange(value);
                                            }}
                                            help={ __( 'Choose how order numbers are generated: Sequential (1, 2, 3…), Order ID, or Random (a unique scrambled number, max 10 digits).', 'custom-order-numbers-for-woocommerce' ) }
                                            __nextHasNoMarginBottom
                                    />
                                ),
                            },
                            {
                                name: 'include_character_enabled',
                                defaultValue: false,
                                showWhen: watch('counter_type') === 'hash_crc32',
                                label: __( 'Include Characters', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <CheckboxControl
                                        label={ __( 'Enable to include charaters in order number', 'custom-order-numbers-for-woocommerce' ) }
                                        checked={ toBoolean(field.value) }
                                        onChange={ field.onChange }
                                    />
                                ),
                            },
                            {
                                name: 'counter',
                                defaultValue: false,
                                showWhen: watch('counter_type') === 'sequential',
                                label: __( 'Next Order Number', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <NumberControl
                                        help={ __( 'The next order placed will be assigned this number. To renumber existing orders, use the Renumber Orders tab.', 'custom-order-numbers-for-woocommerce' ) }
                                        value={ field.value }
                                        onChange={ field.onChange }
                                        min={0}
                                        defaultValue={0}
                                    />
                                ),
                            },
                            {
                                name: 'counter_reset_enabled',
                                defaultValue: 'no',
                                showWhen: watch('counter_type') === 'sequential',
                                label: __( 'Auto-Reset Order Count', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <SelectControl
                                        label={''}
                                        value={field.value}
                                        options={[
                                            { label: __('Disabled', 'custom-order-numbers-for-woocommerce'), value: 'no' },
                                            { label: __('Daily', 'custom-order-numbers-for-woocommerce'), value: 'daily' },
                                            { label: __('Weekly', 'custom-order-numbers-for-woocommerce'), value: 'weekly' },
                                            { label: __('Monthly', 'custom-order-numbers-for-woocommerce'), value: 'monthly' },
                                            { label: __('Yearly', 'custom-order-numbers-for-woocommerce'), value: 'yearly' },

                                        ]}
                                        onChange={(value) => {
                                            field.onChange(value);
                                        }}
                                        help={ __( 'Automatically reset the order number counter at the start of each day, week, month, or year. Useful if you want numbers to restart periodically.', 'custom-order-numbers-for-woocommerce' ) }
                                        __nextHasNoMarginBottom
                                    />
                                ),
                            },
                            {
                                name: 'day_of_counter_reset_weekly',
                                defaultValue: 'mon',
                                showWhen: watch('counter_reset_enabled') === 'weekly',
                                label: __( 'Weekly Counter Reset Day', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <SelectControl
                                        label={''}
                                        value={field.value}
                                        options={[
                                            { label: __('Monday', 'custom-order-numbers-for-woocommerce'), value: 'mon' },
                                            { label: __('Tuesday', 'custom-order-numbers-for-woocommerce'), value: 'tue' },
                                            { label: __('Wednesday', 'custom-order-numbers-for-woocommerce'), value: 'wed' },
                                            { label: __('Thursday', 'custom-order-numbers-for-woocommerce'), value: 'thu' },
                                            { label: __('Friday', 'custom-order-numbers-for-woocommerce'), value: 'fri' },
                                            { label: __('Saturday', 'custom-order-numbers-for-woocommerce'), value: 'sat' },
                                            { label: __('Sunday', 'custom-order-numbers-for-woocommerce'), value: 'sun' },
                                        ]}
                                        onChange={(value) => {
                                            field.onChange(value);
                                        }}
                                        help={ __( 'Select the day of the week on which the order number counter will automatically reset.', 'custom-order-numbers-for-woocommerce' ) }
                                        __nextHasNoMarginBottom
                                    />
                                ),
                            },
                            {
                                name: 'counter_reset_counter_value',
                                defaultValue: 1,
                                showWhen: watch('counter_reset_enabled') !== 'no' && watch('counter_type') === 'sequential',
                                label: __( 'Reset Counter Value', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <NumberControl
                                        help={ __( 'Counter value to reset to. This will be ignored if "Auto-Reset Order Count" option is set to "Disabled".', 'custom-order-numbers-for-woocommerce' ) }
                                        value={ field.value }
                                        onChange={ field.onChange }
                                        min={0}
                                        defaultValue={1}
                                    />
                                ),
                            },
                            {
                                name: 'min_width',
                                defaultValue: 1,
                                label: __( 'Minimum Digits in Order Number', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <NumberControl
                                        help={ __( 'Pad order numbers with leading zeros to reach this length. For example, set to 5 to show "00001" instead of "1". Set to 0 to disable padding. Upgrade to Pro', 'custom-order-numbers-for-woocommerce' ) }
                                        value={ field.value }
                                        onChange={ field.onChange }
                                        min={0}
                                        defaultValue={1}
                                        disabled={true}
                                    />
                                ),
                            },
                            {
                                name: 'settings_to_apply',
                                defaultValue: 'new_order',
                                label: __( 'Apply These Settings To', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <RadioControl
                                        label={''}
                                        selected={field.value}
                                        options={[
                                            { label: __('To all new orders only', 'custom-order-numbers-for-woocommerce'), value: 'new_order' },
                                            { label: __('To orders from a certain order number', 'custom-order-numbers-for-woocommerce'), value: 'order_id' },
                                            { label: __('To orders from a specific date', 'custom-order-numbers-for-woocommerce'), value: 'date' },
                                            { label: __('To all orders', 'custom-order-numbers-for-woocommerce'), value: 'all_orders' },
                                        ]}
                                        onChange={(value) => {
                                            field.onChange(value);
                                        }}
                                        __nextHasNoMarginBottom
                                        help={ __( 'Choose which orders should use your new numbering settings.', 'custom-order-numbers-for-woocommerce' ) }
                                    />
                                ),
                            },
                            {
                                name: 'settings_to_apply_from_order_id',
                                defaultValue: '',
                                showWhen: watch('settings_to_apply') === 'order_id',
                                label: __( 'Starting Order ID', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <NumberControl
                                        help={ __( 'New numbering settings will be applied to all orders from this order ID onwards.', 'custom-order-numbers-for-woocommerce' ) }
                                        value={ field.value }
                                        onChange={ field.onChange }
                                        min={0}
                                    />
                                ),
                            },
                            {
                                name: 'settings_to_apply_from_date',
                                defaultValue: false,
                                showWhen: watch('settings_to_apply') === 'date',
                                label: __( 'Starting Order Date', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <InputControl
                                        help={ __( 'New numbering settings will be applied to all orders from this date onwards. Note : Only applies to past dates.', 'custom-order-numbers-for-woocommerce' ) }
                                        value={ field.value }
                                        onChange={ field.onChange }
                                        type="date"
                                    />
                                ),
                            },
                            {
                                name: 'order_tracking_enabled',
                                defaultValue: false,
                                label: __( 'Order Tracking by Custom Number', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <CheckboxControl
                                        label={ __( 'Enable', 'custom-order-numbers-for-woocommerce' ) }
                                        checked={ toBoolean(field.value) }
                                        onChange={ field.onChange }
                                        help={ __( 'This will allow customers to track their orders using custom order numbers on the "Track your order" page. This page must be created using [woocommerce_order_tracking] shortcode.', 'custom-order-numbers-for-woocommerce' ) }
                                    />
                                ),
                            },
                            {
                                name: 'manual_enabled',
                                defaultValue: false,
                                label: __( 'Set Order Number Manually', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => (
                                    <CheckboxControl
                                        label={ __( 'Enable', 'custom-order-numbers-for-woocommerce' ) }
                                        help={ __( 'Adds an "Order Number" field on each order\'s edit page, so you can enter the number yourself. Requires the Order Number Format to be set to Sequential.', 'custom-order-numbers-for-woocommerce' ) }
                                        checked={ toBoolean(field.value) }
                                        onChange={ field.onChange }
                                        disabled={true}
                                    />
                                ),
                            },
                            {
                                name: 'hide_menu_for_roles',
                                defaultValue: [],
                                label: __( 'Hide Renumber Orders tab for User Roles', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => {
                                    const options = window?.conAdminData?.userRoles || [];
                                    const validValues = options.map( ( o ) => o.value );
                                    const cleanedValues = ( field.value || [] ).filter( ( v ) => validValues.includes( v.value ) );
                                    if ( cleanedValues.length !== ( field.value || [] ).length ) {
                                        field.onChange( cleanedValues );
                                    }
                                    return (
                                        <FormTokenField
                                            label=''
                                            className="con-token-field"
                                            value={ cleanedValues.map( ( v ) => v.label ) }
                                            suggestions={ options.map( ( o ) => o.label ) }
                                            onChange={ ( tokens ) => {
                                                const selected = tokens
                                                    .map( ( token ) => options.find( ( o ) => o.label === token ) )
                                                    .filter( Boolean );
                                                field.onChange( selected );
                                            } }
                                            __experimentalShowHowTo={ false }
                                            placeholder={ __( 'Search for a user role...', 'custom-order-numbers-for-woocommerce' ) }
                                            help={ __( 'Hide "Renumber Orders" admin menu for selected user roles. All user roles are listed here - even those which do not see the menu by default.', 'custom-order-numbers-for-woocommerce' ) }
                                        />
                                    );
                                },
                            },
                            {
                                name: 'hide_tab_for_roles',
                                defaultValue: [],
                                label: __( 'Hide Plugin Settings for User Roles', 'custom-order-numbers-for-woocommerce' ),
                                render: ( field ) => {
                                    const options = window?.conAdminData?.userRoles || [];
                                    const validValues = options.map( ( o ) => o.value );
                                    const cleanedValues = ( field.value || [] ).filter( ( v ) => validValues.includes( v.value ) );
                                    if ( cleanedValues.length !== ( field.value || [] ).length ) {
                                        field.onChange( cleanedValues );
                                    }
                                    return (
                                        <FormTokenField
                                            label=''
                                            className="con-token-field"
                                            value={ cleanedValues.map( ( v ) => v.label ) }
                                            suggestions={ options.map( ( o ) => o.label ) }
                                            onChange={ ( tokens ) => {
                                                const selected = tokens
                                                    .map( ( token ) => options.find( ( o ) => o.label === token ) )
                                                    .filter( Boolean );
                                                field.onChange( selected );
                                            } }
                                            __experimentalShowHowTo={ false }
                                            placeholder={ __( 'Search for a user role...', 'custom-order-numbers-for-woocommerce' ) }
                                            help={ __( 'Hide "Custom Order Numbers" admin settings tab for selected user roles. Tab can not be hidden for administrators. All user roles are listed here - even those which do not see the tab by default.', 'custom-order-numbers-for-woocommerce' ) }
                                        />
                                    );
                                },
                            },
                        ] }
                    />

                    { ! isWcVariant && (
                        <SettingsCardSection
                            heading={ __( 'Usage Data', 'custom-order-numbers-for-woocommerce' ) }
                            control={ control }
                            fields={ [
                                {
                                    name: 'ts_reset_tracking',
                                    defaultValue: false,
                                    render: ( field ) => (
                                        <Button
                                            variant="secondary"
                                            onClick={resetTracking}
                                            help={ __( 'This will reset your usage tracking settings, causing it to show the opt-in banner again and not sending any data.', 'custom-order-numbers-for-woocommerce' ) }
                                        > { __( 'Reset Usage Tracking', 'custom-order-numbers-for-woocommerce' ) }</Button>
                                    ),
                                },
                            ] }
                        />
                    ) }

                    <div
                        ref={ actionsRef }
                        className={ `con-general-actions${ isActionsSticky ? ' is-sticky' : '' }` }
                    >
                        <HStack spacing={3} expanded={false} justify="left">
                            <Button variant="primary" type="submit">{__('Save Changes', 'custom-order-numbers-for-woocommerce')}</Button>
                            <Button
                                variant="secondary"
                                onClick={() => setIsDialogOpen(true)}
                                help={ __( 'Reset plugin settings to default values.', 'custom-order-numbers-for-woocommerce' ) }
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
                            { __( 'Are you sure you want to reset all settings to their default values? This will also clear all prefix/suffix rules.', 'custom-order-numbers-for-woocommerce' ) }
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

export default withNotices(General);