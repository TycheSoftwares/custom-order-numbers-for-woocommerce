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
        question: __( 'I just enabled the plugin, but my existing orders still show their old order numbers. Why?', 'custom-order-numbers-for-woocommerce' ),
        answer: __( 'Existing orders are not modified automatically. The plugin applies custom order numbering only to new orders created after the settings are saved. To apply the same numbering format to existing orders, use the Renumber Orders tool. This will reassign order numbers based on the current configuration.', 'custom-order-numbers-for-woocommerce' ),
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
        question: __( 'I enabled custom order numbers, but new orders still do not start from 1. Why?', 'custom-order-numbers-for-woocommerce' ),
        answer: <>
            <Text>{ __('New orders use the value configured in Next Order Number. If this setting contains a higher number, the sequence will continue from that value instead of starting from 1.', 'custom-order-numbers-for-woocommerce')}</Text>
        </>
    },
    {
        question: __( 'Why are my order numbers restarting automatically?', 'custom-order-numbers-for-woocommerce' ),
        answer: <>
            <Text>{ __( 'Check the Auto-Reset Order Count setting. When enabled, the order number counter can restart daily, weekly, monthly, or yearly.', 'custom-order-numbers-for-woocommerce' )}</Text>
        </>
    },
    {
        question: __( 'I changed my numbering settings, but only some orders are using them. Why?', 'custom-order-numbers-for-woocommerce' ),
        answer: <>
            <Text>{ __( 'The Apply These Settings To option determines which orders use the current numbering configuration. Depending on the selected option, changes may apply only to new orders or to a specific range of orders.', 'custom-order-numbers-for-woocommerce' )}</Text>
        </>
    },
    {
        question: __( 'Can I add a prefix to my order numbers?', 'custom-order-numbers-for-woocommerce' ),
        answer: <>
            <Text>{ __( 'Yes. The Prefix & Suffix Rules tab allows you to add custom prefixes and date-based prefixes to your order numbers. ', 'custom-order-numbers-for-woocommerce' )}</Text>
        </>
    },
    {
        question: __( 'Can I add a suffix to my order numbers?', 'custom-order-numbers-for-woocommerce' ),
        answer: <>
            <Text>{ __( 'Custom suffix rules are available in the Pro version.', 'custom-order-numbers-for-woocommerce' )}</Text>
        </>
    },
    {
        question: __( 'Can I create prefixes based on products, categories, payment methods, or user roles?', 'custom-order-numbers-for-woocommerce' ),
        answer: <>
            <Text>{ __( 'Conditional prefix and suffix rules based on products, categories, payment methods, countries, user roles, and other conditions are available in the Pro version.', 'custom-order-numbers-for-woocommerce' )}</Text>
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
