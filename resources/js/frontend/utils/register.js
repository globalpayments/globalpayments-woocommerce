import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

export const register = ( props ) => {
	const { id, Content, SavedTokenComponent, canMakePayment } = props;
	const settings = getSetting( id + '_data', {} );
	if ( Object.entries(settings).length === 0 ) {
		return;
	}

	const label = decodeEntities( settings.title );
	const Label = ( props ) => {
		const { PaymentMethodLabel } = props.components;
		return <PaymentMethodLabel text={ label } />;
	};

	const PaymentMethod = {
		name: id,
		label: <Label />,
		content: Content,
		edit: Content,
		savedTokenComponent: SavedTokenComponent,
		canMakePayment: () => canMakePayment( settings ),
		ariaLabel: label,
		supports: {
			features: settings.supports,
			showSaveOption: settings.allow_card_saving
		},
	};

	registerPaymentMethod( PaymentMethod );
};
