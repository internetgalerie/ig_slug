import DocumentService from"@typo3/core/document-service.js"
import {default as Modal} from "@typo3/backend/modal.js";

class SlugConfirm {

    constructor(){
	DocumentService.ready().then((()=>{
	    // confirt/rebuild Button
	    let rebuildButton = document.getElementById("slug-rebuild-button");
	    rebuildButton.addEventListener('click', (e) => {
		let rebuildUrl = rebuildButton.dataset.rebuildUrl;
		this.dialog(rebuildButton.dataset.dialogTitle,rebuildButton.dataset.dialogMessage, rebuildUrl);
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

    dialog(title, message, url) {
        let confirmBox = Modal.confirm(title, message);
	    confirmBox.addEventListener("confirm.button.ok",(()=>{
                document.location.href = url,
		confirmBox.hideModal()
	    }));
	confirmBox.addEventListener("confirm.button.cancel",(()=>{
	    confirmBox.hideModal()
	}));
        return false;
    };
}
export default new SlugConfirm;
