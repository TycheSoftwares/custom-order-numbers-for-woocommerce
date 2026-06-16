import {
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalHeading as Heading,
    __experimentalText as Text,
    Button,
    __experimentalGrid as Grid,
    SelectControl,
    RadioControl,
    DatePicker,
    withNotices,
    Spinner,
    Panel,
    PanelBody,
} from "@wordpress/components";
import { __ } from '@wordpress/i18n';
import { useState } from "@wordpress/element";


function Placeholders( { onInsertPlaceholder } ) {

    const placeholders = [
        { category: 'Core', items: ['{prefix}', '{number}', '{suffix}', '{sku}'] },
        {
          category: 'Prefix Variants',
          items: [
            '{date_prefix}',
            '{product_prefix}',
            '{category_prefix}',
            '{user_role_prefix}',
            '{payment_method_prefix}',
            '{country_prefix}',
            '{free_orders_prefix}',
          ],
        },
        {
          category: 'Suffix Variants',
          items: [
            '{date_suffix}',
            '{product_suffix}',
            '{category_suffix}',
            '{user_role_suffix}',
            '{payment_method_suffix}',
            '{country_suffix}',
            '{free_orders_suffix}',
          ],
        },
    ];

    return (
        <VStack spacing={ 8 } className={'con-accordian'}>
            <Panel>
                <PanelBody
                    title={ __( 'Available Placeholders', 'custom-order-numbers-for-woocommerce' ) }
                    initialOpen={ false }
                >
                    <VStack spacing={ 4 }>
                        <Text style={{fontStyle: 'italic'}}>{ __( 'Click any placeholder to insert it at cursor position', 'custom-order-numbers-for-woocommerce' ) }</Text>

                        <Grid templateColumns="repeat(3, 1fr)" gap={ 2 }>
                            { placeholders.map( ( group, index ) => (
                                <VStack key={ index } justify="start">
                                    <Heading level={ 4 }>{ group.category }</Heading>
                                    { group.items.map( ( item, idx ) => (
                                        <Button key={ idx } variant="secondary" size="medium" onClick={ () => onInsertPlaceholder?.( item ) }>
                                            { item }
                                        </Button>
                                    ) ) }
                                </VStack>
                            ) ) }
                        </Grid>
                    </VStack>
                </PanelBody>
            </Panel>
        </VStack>
    );
}

export default withNotices( Placeholders );