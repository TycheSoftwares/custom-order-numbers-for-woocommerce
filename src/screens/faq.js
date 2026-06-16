import {
    Card,
    CardHeader,
    CardBody,
    Panel,
    PanelBody,
    __experimentalVStack as VStack,
    __experimentalHeading as Heading,
    __experimentalText as Text,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const faqs = [
    {
        question: __( 'I just enabled the plugin — why do my existing orders still show old numbers?', 'custom-order-numbers-for-woocommerce' ),
        answer: __( 'Existing orders are not modified automatically. The plugin applies custom order numbering only to new orders created after the settings are saved. To apply the same numbering format to existing orders, use the Renumerate Orders tool. This will reassign order numbers based on the current configuration.', 'custom-order-numbers-for-woocommerce' ),
    },
    {
        question: __( ' I added a prefix or suffix, but it is not showing. What should I check?', 'custom-order-numbers-for-woocommerce' ),
        answer: <>
            <Text>{ __( 'Verify the following settings:', 'custom-order-numbers-for-woocommerce' ) }</Text>
            <p>{ __( '1. Prefix/Suffix option is enabled in the Prefix/Suffix Rules tab', 'custom-order-numbers-for-woocommerce' ) }</p>
            <p>{ __( '2. The format includes {number} (for example: ORD-{number})', 'custom-order-numbers-for-woocommerce' ) }</p>
            <Text>{ __( 'A static value without {number} will not generate a valid order number. After saving changes, create a test order to confirm the updated format.', 'custom-order-numbers-for-woocommerce')}</Text>
        </>
    },
    {
        question: __( 'What happens when I click “Renumber All Orders”? Can it be undone?', 'custom-order-numbers-for-woocommerce' ),
        answer: <>
            <Text>{ __( 'This action reassigns order numbers for all existing orders based on the current configuration.', 'custom-order-numbers-for-woocommerce')}</Text>
            <Text>{ __( 'There is no built-in option to undo this operation.', 'custom-order-numbers-for-woocommerce')}</Text>
            <Text>{ __( 'After execution:', 'custom-order-numbers-for-woocommerce')}</Text>
            <p>{ __( '1. All existing order numbers are replaced', 'custom-order-numbers-for-woocommerce')}</p>
            <p>{ __( '2. Order references in emails and invoices may no longer match', 'custom-order-numbers-for-woocommerce')}</p>
            <p>{ __( '3. External systems relying on order numbers may lose synchronization', 'custom-order-numbers-for-woocommerce')}</p>
        </>
    },
    {
        question: __( 'I changed the Next Order Number and now see duplicate order numbers. How can this be fixed?', 'custom-order-numbers-for-woocommerce' ),
        answer: <>
            <Text>{ __('This occurs when the configured value overlaps with existing order numbers.', 'custom-order-numbers-for-woocommerce')}</Text>
            <Text>{ __('To resolve this:', 'custom-order-numbers-for-woocommerce')}</Text>
            <p>{ __('1. Identify the latest order number in the store', 'custom-order-numbers-for-woocommerce')}</p>
            <p>{ __('2. Set “Next Order Number” to a value higher than the latest order', 'custom-order-numbers-for-woocommerce')}</p>
            <p>{ __('3. Save changes', 'custom-order-numbers-for-woocommerce')}</p>
            <Text>{ __( 'New orders will follow the corrected sequence.', 'custom-order-numbers-for-woocommerce')}</Text>
        </>
    },
    {
        question: __( 'My license shows inactive even after entering the key. What should I check?', 'custom-order-numbers-for-woocommerce' ),
        answer: <>
            <Text>{ __( 'Check the following conditions:', 'custom-order-numbers-for-woocommerce' )}</Text>
            <p>{ __('1. No extra spaces are present in the license key', 'custom-order-numbers-for-woocommerce')}</p>
            <p>{ __('2. The license has not exceeded its activation limit', 'custom-order-numbers-for-woocommerce')}</p>
            <p>{ __('3. The server allows outbound requests for license validation', 'custom-order-numbers-for-woocommerce')}</p>
            <Text>{ __( 'If the issue persists, contact support with the purchase order number and domain details.', 'custom-order-numbers-for-woocommerce')}</Text>
        </>
    },
    {
        question: __( ' What happens if I disable or uninstall the plugin?', 'custom-order-numbers-for-woocommerce' ),
        answer: <>
            <Text>{ __( 'Existing order numbers remain unchanged', 'custom-order-numbers-for-woocommerce' )}</Text>
            <p>{ __( '1. If disabled — New orders will use WooCommerce default numbering', 'custom-order-numbers-for-woocommerce')}</p>
            <p>{ __( '2. If uninstalled — Plugin settings are removed, but existing order numbers remain stored on orders', 'custom-order-numbers-for-woocommerce')}</p>
        </>
    },
];

function Faq() {
    return (
        <VStack style={{ margin: '30px' }}>
            <Card>
                <CardHeader>
                    <VStack spacing={ 2 }>
                        <Heading level={ 4 }>
                            { __( 'Frequently Asked Questions', 'custom-order-numbers-for-woocommerce' ) }
                        </Heading>
                        <Text className="components-text">
                            { __( 'Find answers to the most common questions about Custom Order Numbers for WooCommerce.', 'custom-order-numbers-for-woocommerce' ) }
                        </Text>
                    </VStack>
                </CardHeader>

                <CardBody style={{ padding: 0 }}>
                    <Panel>
                        { faqs.map( ( faq, index ) => (
                            <PanelBody
                                key={ index }
                                title={ faq.question }
                                initialOpen={ false }
                            >
                                <Text>{ faq.answer }</Text>
                            </PanelBody>
                        ) ) }
                    </Panel>
                </CardBody>
            </Card>
        </VStack>
    );
}

export default Faq;
