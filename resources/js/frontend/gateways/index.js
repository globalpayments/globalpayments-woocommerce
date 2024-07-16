import { register } from '../utils/register';
import { Content } from './component';

const gatewayIds = [ 'globalpayments_gpapi' ];

for ( let gatewayId of gatewayIds ) {
	const props = {
		id: gatewayId,
		Content: <Content id={ gatewayId } />
	}
	register( props );
}
