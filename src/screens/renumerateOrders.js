import { useEffect } from "@wordpress/element";
import { useForm } from "react-hook-form";
import {
    Card,
    CardHeader,
    CardBody,
    __experimentalText as Text,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalHeading as Heading,
    Button,
    Notice,
    withNotices
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { renumerateOrders } from "../data/api";

function RenumerateOrders( { noticeOperations, noticeUI } ) {

    const { handleSubmit, formState: { isSubmitting } } = useForm({
        defaultValues: {},
    });

    // Auto-dismiss notices after 4 seconds.
    useEffect( () => {
        if ( noticeUI ) {
            const timer = setTimeout( () => {
                noticeOperations.removeAllNotices();
            }, 4000 );
            return () => clearTimeout( timer );
        }
    }, [ noticeUI ] );

    const onSubmit = async () => {
        try {
            const response = await renumerateOrders();
            noticeOperations.removeAllNotices();
            if ( response.scheduled ) {
                noticeOperations.createNotice( {
                    status: 'info',
                    content: __( 'Custom order numbers are being renumbered in the background.', 'custom-order-numbers-for-woocommerce' ),
                } );
            } else {
                if ( response.error ) {
                    noticeOperations.createNotice( {
                        status: 'error',
                        content: __( `Error renumbering orders: ${ response.error }`, 'custom-order-numbers-for-woocommerce' ),
                    } );
                    return;
                }
                noticeOperations.createNotice( {
                    status: 'success',
                    content: __( `Renumeration complete. ${ response.total_renumerated } order(s) updated.`, 'custom-order-numbers-for-woocommerce' ),
                } );
            }
        } catch ( error ) {
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( {
                status: 'error',
                content: error?.message || __( 'Error renumbering orders.', 'custom-order-numbers-for-woocommerce' ),
            } );
        }
    };

    const onError = () => {
        noticeOperations.removeAllNotices();
        noticeOperations.createNotice( {
            status: 'error',
            content: __( 'Error renumbering orders.', 'custom-order-numbers-for-woocommerce' ),
        } );
    };

    return (
        <VStack style={ { margin: '30px' } }>
            <div style={ { position: 'fixed', bottom: '20px', right: '20px', zIndex: 9999, maxWidth: '400px' } }>
                { noticeUI }
            </div>
            <form onSubmit={ handleSubmit( onSubmit, onError ) }>
                <VStack className={ 'con_setting_section' } spacing={ 10 }>

                    <Card className="pif-field-builder con-renumerate-card">
                        <CardHeader>
                            <VStack spacing={ 2 }>
                                <Heading level={ 4 }>{ __( 'Renumber Orders', 'custom-order-numbers-for-woocommerce' ) }</Heading>
                                <Text className="components-text">
                                    { __( 'Use this tool to reassign order numbers to all your existing orders in sequence.', 'custom-order-numbers-for-woocommerce' ) }
                                </Text>
                            </VStack>
                        </CardHeader>
                        <CardBody className="con-renumerate-content">
                            <VStack style={ { marginBottom: '20px' } }>
                                <Notice className="con-renumerate-warning" status="warning" isDismissible={ false }>
                                    <strong>{ __( 'Important:', 'custom-order-numbers-for-woocommerce' ) }</strong>{ ' ' }
                                    { __( 'Before proceeding, please ensure you have created a complete backup of your database. This tool will modify existing order numbers and cannot be easily undone.', 'custom-order-numbers-for-woocommerce' ) }
                                </Notice>
                            </VStack>

                            <VStack style={ { marginBottom: '20px' } }>
                                <Text isBlock={ true }>{ __( 'With the help of this tool, you can change the custom order numbers for all the existing orders in a sequence and will maintain the sequence for the upcoming orders also.', 'custom-order-numbers-for-woocommerce' ) }</Text>
                                <Text isBlock={ true }>{ __( 'The starting order number will always be 1, and all orders will be numbered sequentially from there.', 'custom-order-numbers-for-woocommerce' ) }</Text>
                            </VStack>

                            <div className="con-renumerate-divider"></div>

                            <HStack spacing={ 3 } expanded={ false } justify="left">
                                <Button
                                    className="con-renumerate-button"
                                    variant="primary"
                                    type="submit"
                                    isBusy={ isSubmitting }
                                    disabled={ isSubmitting }
                                >
                                    { __( 'Renumber Orders', 'custom-order-numbers-for-woocommerce' ) }
                                </Button>
                            </HStack>
                        </CardBody>
                    </Card>

                </VStack>
            </form>
            <style>
                { `
                    tr:not(:last-child) td {
                        padding-bottom: 30px;
                    }
                    td:nth-child(2) {
                        padding-left: 30px;
                    }
                ` }
            </style>
        </VStack>
    );
}

export default withNotices( RenumerateOrders );