/**
 * WordPress dependencies.
 */
import {
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
	__experimentalHeading as Heading,
	__experimentalText as Text,
	Icon,
	Card,
	CardBody,
} from '@wordpress/components';

/**
 * External dependencies.
 */
import { NavLink } from 'react-router-dom';

/**
 * Header component — plugin title, description, and tab navigation.
 *
 * @param {Object}   props
 * @param {string}   props.title       Plugin title.
 * @param {string}   props.description Short description shown below the title.
 * @param {Array}    props.tabs        Array of { name, title, path } tab objects.
 */
const Header = ( { title, description, tabs } ) => {
	return (
		<div className="con-plugin-header">
			<VStack spacing={ 1 } className="con-plugin-header__title-wrap">
				<Heading level={ 1 } className="con-plugin-header__title">
					{ title }
				</Heading>
				{ description && (
					<Text className="con-plugin-header__description">
						{ description }
					</Text>
				) }
			</VStack>

			<HStack className="con-plugin-header__tabs-row">
				<div className="con-header-dashboard-tabs">
					{ tabs.map( ( tab ) => (
						<NavLink
							key={ tab.name }
							to={ tab.path }
							className={ ( { isActive } ) =>
								'con-dashboard-tab' + ( isActive ? ' is-active' : '' )
							}
						>
							{ tab.icon && (
								<Icon
									icon={ tab.icon }
									size={ 16 }
									className="con-dashboard-tab__icon"
								/>
							) }
							{ tab.title }
						</NavLink>
					) ) }
				</div>
			</HStack>
		</div>
	);
};

export default Header;
