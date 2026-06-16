import { 
    Card, 
    CardHeader, 
    CardBody, 
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalHeading as Heading, 
    __experimentalText as Text, 
    __experimentalGrid as Grid,
    __experimentalNumberControl as NumberControl,
    __experimentalInputControl as InputControl,
    SelectControl,
    Button,
    FormFileUpload,
    DropZone,
    Tooltip,
    Icon
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from "@wordpress/element";

import { Controller, useFieldArray } from 'react-hook-form';

const OptionsTable = ({
    id,
    control,
    className = '',
    columns,
    templateColumns,
    optionFields,
    defaultValue = {}
}) => {

    const { fields, append, remove, update } = useFieldArray({
        name: id,
        control,
    });

    useEffect(() => {
        if (fields.length === 0 && defaultValue && Object.keys(defaultValue).length > 0) {
            append( defaultValue );
        }
    }, []);

    const upgradeUrl = window?.conAdminData?.upgradeUrl || '#';

    const renderColumnHeading = ( column, index ) => {
        const columnLabel = typeof column === 'string' ? column : column?.label;
        const columnTooltip = typeof column === 'string' ? '' : column?.tooltip;
        const isPro = typeof column === 'object' && column?.isPro;

        return (
            <HStack key={ index } alignment="left" spacing={ 1 } style={{ flexWrap: 'wrap', rowGap: '2px' }}>
                <Text>{ columnLabel }</Text>
                { columnTooltip && (
                    <Tooltip text={ columnTooltip }>
                        <span style={{ display: 'inline-flex', cursor: 'help', color: '#757575' }}>
                            <Icon icon="info-outline" size={ 14 } />
                        </span>
                    </Tooltip>
                ) }
                { isPro && (
                    <a
                        href={ upgradeUrl }
                        target="_blank"
                        rel="noopener noreferrer"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: '3px', fontSize: '11px', fontWeight: 600, color: '#fff', background: '#3858e9', borderRadius: '3px', padding: '1px 5px', textDecoration: 'none', lineHeight: '1.6' }}
                    >
                        <Icon icon="lock" size={ 11 } />
                        PRO
                    </a>
                ) }
            </HStack>
        );
    };

    return (
        <>
            <Card>
                <CardHeader style={{ display: 'block', background: '#f9fafb'}}>
                    <Grid templateColumns={templateColumns}>
                        {
                            columns && columns.map((column, index) => renderColumnHeading(column, index))
                        }
                    </Grid>
                </CardHeader>
                <CardBody>
                    {
                        fields.map((item, index) => {
                            return (
                                <Grid id={item.id} key={item.id} templateColumns={templateColumns} templateRows={'1fr auto'} align='center' justify='center'>
                                    {
                                        optionFields && optionFields.map((field, fieldIndex) => (
                                            field.showWhen === undefined || field.showWhen ? (
                                                <Controller
                                                    name={`${id}.${index}.${field.name}`}
                                                    control={control}
                                                    defaultValue={ field.defaultValue }
                                                    render={ ( { field: controllerField } ) =>
                                                        field.render( controllerField, index )
                                                    }
                                                />
                                        ) : null
                                            
                                        ))
                                    }
                                    <HStack>
                                        <Button icon={'trash'} isDestructive style={{ marginTop: 'auto' }} onClick={() => remove(index)} disabled={ 'prefix_suffix_rules' === id ? true : false } />
                                    </HStack>
                                </Grid>
                            )
                        })
                    }
                </CardBody>
            </Card>
            <HStack> 
                <Button className='con-button-add-option' onClick={() => append( defaultValue )} disabled={ 'prefix_suffix_rules' === id ? true : false }>{__('+ Add Rule', 'custom-order-numbers-for-woocommerce')}</Button>
            </HStack>
        </>
    );
};

export default OptionsTable;