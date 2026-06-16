/**
 * WordPress dependencies.
 */
import {
    Card,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalText as Text,
    ExternalLink,
    CardHeader,
    CardBody,
    CardFooter,
} from '@wordpress/components';

import { useRef, useEffect, useState } from "@wordpress/element";
import { __ } from '@wordpress/i18n';
import { cog, filter, reusableBlock, key, help } from '@wordpress/icons';
import { General, ConditionalRules, RenumerateOrders, License, Faq } from './screens';
import { Header } from './components';
import { getSettings, getLicense } from './data/api';

/**
 * External dependencies
 */
import { Navigate, Route, Routes } from 'react-router-dom';

function App() {
    const parentRef = useRef(null);

    const [data, setData] = useState(null);
    const [isLoading, setIsLoading] = useState(true);

    const refreshSettings = async (withLoader = false) => {
        if (withLoader) {
            setIsLoading(true);
        }

        try {
            const settings = await getSettings();
            setData(settings);
            return settings;
        } finally {
            if (withLoader) {
                setIsLoading(false);
            }
        }
    };

    useEffect(() => {
        refreshSettings(true);
    }, []);


    const currentUserRoles = window?.conAdminData?.currentUserRoles || [];
    const hideTabRoleSlugs = ( data?.hide_menu_for_roles || [] ).map( ( r ) => r.value );
    const shouldHideRenumerateTab = hideTabRoleSlugs.some( ( slug ) => currentUserRoles.includes( slug ) );
    const isWcVariant = !! window?.conAdminData?.isWcVariant;

    const tabs = [
        {
            name: 'general',
            title: __('General', 'custom-order-numbers-for-woocommerce'),
            path: '/',
            icon: cog,
        },
        {
            name: 'rules',
            title: __('Prefix/Suffix Rules', 'custom-order-numbers-for-woocommerce'),
            path: '/rules',
            icon: filter,
        },
        {
            name: 'renumerate',
            title: __('Renumber Orders', 'custom-order-numbers-for-woocommerce'),
            path: '/renumerate',
            icon: reusableBlock,
        },
        {
            name: 'faq',
            title: __('FAQ', 'custom-order-numbers-for-woocommerce'),
            path: '/faq',
            icon: help,
        },
    ].filter( ( tab ) => {
        if ( tab.name === 'renumerate' && shouldHideRenumerateTab ) return false;
        return true;
    } );

    return (
        <>
            <Header
                title={ __( 'Custom Order Numbers for WooCommerce', 'custom-order-numbers-for-woocommerce' ) }
                description={ __( 'Customize your WooCommerce order numbers with prefixes, suffixes, and sequential numbering.','custom-order-numbers-for-woocommerce' ) }
                tabs={ tabs }
            />

            <VStack>

                <Routes>
                    <Route path='/' element={<General parentRef={parentRef} settingsData={data || null} onSettingsSaved={refreshSettings} />}></Route>
                    <Route path='/rules' element={<ConditionalRules parentRef={parentRef} settingsData={data || null} onSettingsSaved={refreshSettings} />}></Route>
                     <Route path='/renumerate' element={ shouldHideRenumerateTab ? <Navigate to={'/'} replace /> : <RenumerateOrders /> }></Route>
                     <Route path='/faq' element={<Faq />}></Route>
                     <Route path='*' element={<Navigate to={'/'} replace />}></Route>
                </Routes>
            
            </VStack>

            <VStack style={{ padding: "20px 0" }}>
                <HStack justify="center" style={{ marginBottom: "22px" }}>
                    <ExternalLink href="https://support.tychesoftwares.com/help/2285384554/" className="bogo-link">
                    Need support?
                    </ExternalLink>
                    <Text style={{ fontWeight: "bold" }}>
                    We’re always happy to help you.
                    </Text>
                </HStack>
                <HStack justify="center">
                    <Text>If this plugin helped you,</Text>
                    <ExternalLink href="https://wordpress.org/support/plugin/custom-order-numbers-for-woocommerce/reviews" className="bogo-link">
                    please rate it
                    </ExternalLink>
                    <Text style={{ fontSize: "17px", color: "#FFBA00" }}>★★★★★</Text>
                </HStack>
            </VStack>
        </>
    );
}

export default App;