import DocumentService from "@typo3/core/document-service.js"
import Modal from "@typo3/backend/modal.js";
import Severity from '@typo3/backend/severity.js';
import {lll} from '@typo3/core/lit-helper.js';

class SlugConfirm {

    constructor(){
	DocumentService.ready().then((()=>{
	    // confirt/rebuild Button
	    let rebuildButton = document.getElementById("slug-rebuild-button");
	    rebuildButton.addEventListener('click', (e) => {
		let rebuildUrl = rebuildButton.dataset.rebuildUrl;
		this.dialog(rebuildUrl);
	    });
	    // filters reload on changes
	    let filterForm = document.getElementById("ig_slug_filters");
	    let filters = document.querySelectorAll("select.form-ig-slug-filter");
	    filters.forEach((filter) => {
		filter.addEventListener('change', (e) => {
		    filterForm.submit();
		})
	    });	    
	}));
    };

    dialog(rebuildUrl) {
	const tableSelect = document.getElementById('ig-slug-table-filter');
	const isPages = tableSelect?.value === 'pages';
	const part = isPages ? 'page' : 'entry';

	const buttons = [
            {
		text: lll('button.cancel'),
		btnClass: 'btn-default',
		trigger: () => {
                    Modal.dismiss();
		},
            },
	];

	if (isPages) {
	    let rebuildAndRedirectsUrl = rebuildUrl + '&autoCreateRedirects=1';
            buttons.push({
		text: lll('igSlug.button.updateAndRedirects'),
		btnClass: 'btn-danger',
		trigger: () => {
                    document.location.href = rebuildAndRedirectsUrl;
                    Modal.dismiss();
		},
            });
	}

	buttons.push({
            text: lll(isPages ? 'igSlug.button.updateOnly' : 'igSlug.button.update'),
	    btnClass: 'btn-primary',
	    trigger: () => {
		document.location.href = rebuildUrl;
                Modal.dismiss();
	    },
	});

	const confirmBox = Modal.confirm(
            lll(`igSlug.confirm.${part}.title`),
            lll(`igSlug.confirm.${part}.message`),
	    Severity.warning,
	    buttons
	);
    };
}
export default new SlugConfirm;
