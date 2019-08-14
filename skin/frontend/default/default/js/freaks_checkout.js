/**
 * 0 Step Checkout frontend controller
 *
 * @category    FI
 * @package     FI_Checkout
 * @author      Sergiy Stotskiy <sergiy.stotskiy@freaksidea.com>
 * @copyright   Copyright (c) 2012 Sergiy Stotskiy (http://freaksidea.com)
 */
(function(e){var g=typeof window.localStorage!=="undefined";function h(k,i){var j;if(typeof k==="string"&&typeof i==="string"){localStorage[k]=i;return true}else{if(typeof k==="object"&&typeof i==="undefined"){for(j in k){if(k.hasOwnProperty(j)){localStorage[j]=k[j]}}return true}}return false}function b(m,i){var j,k,l;j=new Date();j.setTime(j.getTime()+31536000000);k="; expires="+j.toGMTString();if(typeof m==="string"&&typeof i==="string"){document.cookie=m+"="+i+k+"; path=/";return true}else{if(typeof m==="object"&&typeof i==="undefined"){for(l in m){if(m.hasOwnProperty(l)){document.cookie=l+"="+m[l]+k+"; path=/"}}return true}}return false}function d(i){return localStorage[i]}function c(o){var m,j,k,l;m=o+"=";j=document.cookie.split(";");for(k=0;k<j.length;k++){l=j[k];while(l.charAt(0)===" "){l=l.substring(1,l.length)}if(l.indexOf(m)===0){return l.substring(m.length,l.length)}}return null}function f(j){try{delete localStorage[j]}catch(i){localStorage[j]=""}}function a(i){return b(i,"",-1)}e.Storage={set:g?h:b,get:g?d:c,remove:g?f:a}})(Object);Element.prototype.triggerEvent=function(a){if(document.createEvent){var b=document.createEvent("HTMLEvents");b.initEvent(a,true,true);return this.dispatchEvent(b)}if(document.createEventObject){var b=document.createEventObject();return this.fireEvent("on"+a,b)}};var FreaksTabs=Class.create();FreaksTabs.prototype={initialize:function(c,b){this.onChangeTab=null;this.forms={};this.params=Object.extend({currentClassName:"current",contentTagName:"div"},b||{});if(!c){return false}this.tabsBlock=$(c);var a=this;this.tabsBlock.observe("click",function(f){var d=Event.element(f);if(d.nodeName.toLowerCase()!="a"){return true}a.setCurrentTab(d.href.substr(d.href.lastIndexOf("#")+1));Event.stop(f)})},setCurrentTab:function(c){var b=this.getTab(c);if(b.button&&b.content&&b.button!=this.getCurrentTab()){var a={button:this.getCurrentTab(),content:this.getCurrentContent()};a.content.hide();a.button.removeClassName(this.params.currentClassName);$(b.button.parentNode).addClassName(this.params.currentClassName);$(b.content).show();if(this.onChangeTab&&this.onChangeTab.call){this.onChangeTab({prev:a,current:b})}}},getCurrentTab:function(){if(this.tabsBlock){return this.tabsBlock.select("."+this.params.currentClassName)[0]}return null},getCurrentContent:function(){var a=this.getCurrentTab().firstDescendant(),b=a.href.substr(a.href.lastIndexOf("#")+1);return $(this.tabsBlock.parentNode).select(this.params.contentTagName+"."+b)[0]},getTab:function(c){var a=$(this.tabsBlock.parentNode).select(this.params.contentTagName+"."+c)[0],b="";this.tabsBlock.select("a").each(function(d){var f=d.href.substr(d.href.lastIndexOf("#")+1);if(f==c){b=d;return false}});return{button:b,content:a}},setTabForm:function(b,a){this.forms[b]=a;return this},getTabForm:function(a){return this.forms[a]},getTabForms:function(){return this.forms},getCurrentForm:function(){var b=this.getCurrentTab();if(!b){return null}var a=b.firstDescendant(),c=a.href.substr(a.href.lastIndexOf("#")+1);return this.forms[c]}};RestorableForm=Class.create();RestorableForm.Storage=Object.Storage;RestorableForm.prototype=new VarienForm();RestorableForm.prototype.initialize=(function(a){return function(c,b){a.call(this,c,b);this.formId=c;if(this.form){this.restoreValues();this.form.observe("submit",this.submit.bindAsEventListener(this));this.embededForms={};this.hasEmbededForms=false;this.responsePopup=null}}})(VarienForm.prototype.initialize);RestorableForm.prototype.getKey=function(a){return this.formId+"-"+a};RestorableForm.prototype.clearValues=function(c){var b=c.length,a="";while(b--){a=this.getKey(c[b]);if(RestorableForm.Storage.get(a)){RestorableForm.Storage.remove(a)}}return this};RestorableForm.prototype.restoreValues=function(){var a=this,b=function(d){var c=$(this),f=c&&c.nodeName&&c.nodeName.toLowerCase();if(f!="input"&&f!="select"&&f!="textarea"){c=c.select("input")[0]}RestorableForm.Storage.set(a.getKey(c.name),c.value)};Form.getElements(this.form).each(function(d){if(d.hasClassName("non-storable")){return true}var c=RestorableForm.Storage.get(a.getKey(d.name));if(c){if(d.type=="radio"||d.type=="checkbox"){if(d.value==c){d.checked=true}}else{d.value=c}}if(d.type=="radio"||d.type=="checkbox"){d.up().observe("click",b)}else{d.observe("change",b)}});return this};RestorableForm.prototype.submit=function(c,b){var g=this.isValid();if(g){var h=this,a=this.form,d=function(k){var j=h.getKey(k.name);if(RestorableForm.Storage.get(j)){RestorableForm.Storage.remove(j)}},f=function(j){if(j.disabled||(j.type=="radio"||j.type=="checkbox")&&!j.checked){return}var k=a[j.name];if(!k){k=new Element("input");k.type="hidden";k.name=j.name;a.appendChild(k)}k.value=j.value};Form.getElements(this.form).each(d);if(this.hasEmbededForms){for(var e in this.embededForms){var i=this.embededForms[e];Form.getElements(i.form).each(d).each(f)}}if(c){this.request()}else{this.form.submit()}}return g};RestorableForm.prototype.request=function(){return new Ajax.Request(this.form.getAttribute("action"),{method:this.form.getAttribute("method"),parameters:Form.serialize(this.form,true),onComplete:(function(b){try{var a=b.responseText.evalJSON()}catch(c){return}if(a.redirect){location.href=a.redirect}else{if(a.error_message){var d=this.form.previous("ul.messages");if(d){d.up().removeChild(d)}this.form.insert({before:a.error_message});this.form.up(1).scrollTo()}}this.form.fire("form:has_response",a)}).bind(this)})};RestorableForm.prototype.isValid=function(){var b=this.validator&&this.validator.validate();if(b&&this.hasEmbededForms){for(var c in this.embededForms){var a=this.embededForms[c];b=b&&a.validator&&a.validator.validate();if(!b){break}}}return b};RestorableForm.prototype.addEmbededForm=function(a){if(a.form){this.hasEmbededForms=true;this.embededForms[a.form.id]=a}return this};RestorableForm.prototype.getEmbededForms=function(){return this.embededForms};RestorableForm.prototype.getEmbededForm=function(a){return this.embededForms[a]};var fiSelectBox=Class.create({initialize:function(a){this.element=$(a);if(this.element){this.clearInputValueIfNotInList();this.render();this.addSelectObserver(true)}},clearInputValueIfNotInList:function(){var b=$(this.element).previous('input[type="text"]'),a=b.value.strip().replace(/"/g,'\\"');if(this.element.select("option").length&&!this.element.select('option:contains("'+a+'")').length){b.value=""}},addSelectObserver:function(a){var b=function(d,c){var f=$(this).previous('input[type="text"]');if(this.selectedIndex>=0&&this.value!=0){f.value=this.options[this.selectedIndex].innerHTML}else{if(!c){f.value=""}}if(!c){f.fire("fiSelectBox:change",{event:d})}};this.element.observe("change",b);b.call(this.element,null,a);return this},render:function(a){if(a){var b=this.element;b.insert({after:a});this.element=b.next();b.up().removeChild(b);this.addSelectObserver()}if(!this.element.getElementsByTagName("option").length){this.element.disabled=true;this.element.hide()}else{this.element.disabled=false;this.element.show()}return this},addChangeListener:function(a){if(this.element){var b=this.element.previous('input[type="text"]');b.observe("fiSelectBox:change",a.bind(this))}return this},dependsOn:function(b,d,c){d=d||"value";c=c||"input-loading";var e={},a=this;b&&b.observe("change",function(g){var f=a.element.previous('input[type="text"]');e[d]=this.value;f.addClassName(c);f.value="";f.disabled=true;new Ajax.Request(this.parentNode.getAttribute("data-action"),{method:"get",parameters:e,onComplete:function(h){f.removeClassName(c);f.disabled=false;a.render(h.responseText)}})});return this}});var FreaksAutocompleter=Class.create(Ajax.Autocompleter,{startIndicator:function(){if(this.options.indicator){this.element.addClassName(this.options.indicator)}},stopIndicator:function(){if(this.options.indicator){this.element.removeClassName(this.options.indicator)}},showResults:function(){this.hasFocus=true;this.changed=false;this.startIndicator();this.options.parameters=encodeURIComponent(this.options.paramName)+"="+encodeURIComponent(this.element.value);if(this.options.defaultParams){this.options.parameters+="&"+this.options.defaultParams}new Ajax.Request(this.url,this.options);setTimeout(this.stopIndicator.bind(this),1500)},addChangeListener:function(a){Event.observe(this.element,"change",(function(c){if(this.changeTimeout){clearTimeout(this.changeTimeout)}var b=this;this.changeTimeout=setTimeout(function(){a.call(b,c)},100)}).bindAsEventListener(this));return this},addShowResults:function(a){Event.observe(this.element.next(a),"click",(function(b){Event.stop(b);this.showResults()}).bindAsEventListener(this));return this},updateElement:function(d){var c="";if(this.options.select){var a=$(d).select("."+this.options.select)||[];if(a.length>0){c=Element.collectTextNodes(a[0],this.options.select)}}else{c=Element.collectTextNodesIgnoreClass(d,"informal")}var b=this.element.value.split(/\s*,\s*/);if(c!=b[b.length-2]){b[b.length-1]=c}this.element.value=b.join(", ");this.oldElementValue=this.element.value;this.element.focus();this.element.triggerEvent("change")}});var fiCheckoutViewModel=Class.create({initialize:function(a){this.updateUrl="";this.sections={};this.requests={};this.createSections(a)},isActive:function(){return this.getUrl()!=null},createSections:function(b){for(var a in b){if(b[a]){this.sections[a]=b[a]}}return this},findUrl:function(){var a="";for(var b in this.sections){var c=this.sections[b];if(a=c.getAttribute("data-action")){break}}return a},getUrl:function(){if(this.updateUrl){return this.updateUrl}return this.updateUrl=this.findUrl()},updateView:function(d){for(var b in this.sections){var a=d[b],c=this.sections[b];if(a){if(c.nodeName.toLowerCase()=="table"){c.insert({after:a});this.sections[b]=c.next();c.up().removeChild(c);c=this.sections[b]}else{c.update(a)}}c.up().removeClassName("loading");c.setStyle({height:"",visibility:"visible"})}},ajaxCallback:function(b){var a=b.responseText;try{a=a.evalJSON();this.updateView(a);$(document).fire("fiCheckout:updated",{viewModel:this})}catch(c){}},waiting:function(b){var a=[];if(b){var c=this.sections[b];c.setStyle({height:c.getHeight()+"px",visibility:"hidden"});$(c.parentNode).addClassName("loading");a.push(b)}else{for(var b in this.sections){var c=this.sections[b];c.setStyle({height:c.getHeight()+"px",visibility:"hidden"});$(c.parentNode).addClassName("loading");a.push(b)}}return a},request:function(b,a){return new Ajax.Request(this.getUrl(),{method:"post",parameters:b,onComplete:a?a.bind(this):this.ajaxCallback.bind(this),onFailure:function(){location.href=location.href}})},sendOnceFor:function(a,b){if(this.requests[a]){this.requests[a].transport.abort()}this.requests[a]=this.request(b,function(){delete this.requests[a];this.ajaxCallback.apply(this,arguments)});return this.requests[a]},getSection:function(a){return this.sections[a]||null},updateSection:function(a,c){var b=this.sections[a];this.waiting(a);c=c||{};c.type=a;return this.sendOnceFor(a,c)},updateAll:function(b){var a=this.waiting();b=b||{};b.type=a.join(",");return this.sendOnceFor(b.type,b)},processing:function(b){var a=this.disableButton(b);if(a){a.previous(".process").show()}},stopProcessing:function(b){var a=$(b);if(a){a.previous(".process").hide()}},disableButton:function(b){var a=$(b);if(a){a.addClassName("disabled");a.disabled=true;a.up("ul").select("li").each(function(c){if(c!=a.parentNode){c.setStyle({visibility:"hidden"})}})}return a},enableButton:function(b){var a=$(b);if(a){a.removeClassName("disabled");a.disabled=false;a.up("ul").select("li").each(function(c){if(c!=a.parentNode){c.setStyle({visibility:"visible"})}})}return a},getChangeListener:function(){var a=this;return function(){var b=["country_id","region","postcode","city"],d=b.length,c=this.element||this,f={};if(c.value.strip()&&c.value!="0"){f[c.name]=c.value;while(d--){var e=c.form["user[address]["+b[d]+"]"];if(e&&e.value){f[e.name]=e.value}}a.updateAll(f);a.disableButton("place-order")}}}});fiCheckoutViewModel.isElement=function(b,a){var c=b&&b.nodeName.toLowerCase();return c=="input"&&b.type==a};var fiPaymentViewModel=Class.create({initialize:function(a){this.element=a;this.currentMethod="";this.restore()},getCurrentMethod:function(){return this.currentMethod},getCurrentMethodElement:function(){return this.element&&this.element.select('input[type="radio"]:checked')[0]},findCurrentMethod:function(){var a=this.getCurrentMethodElement();return a?a.value:""},getAdditionalForm:function(){return $("payment_form_"+this.currentMethod)},enableFormElements:function(d,a){var c=Form.getElements(d),b=c.length;while(b--){c[b].disabled=!a}d[a?"show":"hide"]();return this},showAdditionalForm:function(a){var b=this.getAdditionalForm();if(b){this.enableFormElements(b,a)}return this},setMethod:function(b){this.currentMethod=b;var a=this.getAdditionalForm();$(a||document.body).fire("payment-method:switched",{method_code:b});return this},switchMethod:function(a){this.showAdditionalForm(false);this.setMethod(a);this.showAdditionalForm(true)},restore:function(){var a=this;if(this.element){this.switchMethod(this.findCurrentMethod());this.element.select(".form-list").each(function(b){a.enableFormElements(b,b.offsetWidth!=0)})}return this}});var fiCentinelViewModel=Class.create({initialize:function(c){this.element=c;this.isValid=false;if(this.element){var b=this.element.up().select("a.popup-close")[0],a=this;b.observe("click",function(d){$(this).up().hide();Event.stop(d);a.element.fire("fiCentinel:cancel")})}},validate:function(b,c){var a=this;a.element.setStyle({visibility:"hidden"});a.element.up().addClassName("loading");a.element.up().setStyle({display:"block"});return new Ajax.Request(b,{method:"post",parameters:c,onComplete:function(f){try{var d=f.responseText.evalJSON()}catch(g){return}a.element.update(d.html);a.element.setStyle({visibility:"visible"});a.element.up().removeClassName("loading");if(d.url){a.process(d.url)}else{a.success()}}})},idle:function(){this.element.up().hide()},process:function(a){var b=this.element.select("iframe")[0];b.src=a;return this},moveElement:function(a,b){if(this.element){this.element.up().setStyle({top:a+"px",left:b+"px"})}return this},isSuccessfull:function(){return this.isValid},resetValidation:function(){this.isValid=false;return this},success:function(){if(this.element){setTimeout((function(){this.element.up().hide()}).bind(this),2000);this.isValid=true;this.element.fire("fiCentinel:success")}}});var fiPage=Class.create({addFieldPopup:function(a,c){if(!a.form){return false}var b=$(a.form[c]);if(!b||!b.next(".popup")){return false}b.observe("focus",function(){$(this).next(".popup").show()});b.observe("blur",function(){$(this).next(".popup").hide()});return b},createTabs:function(d,h,a){var c=new FreaksTabs(d),e="";c.onChangeTab=function(j){if(j.prev.content.hasClassName("new-user")&&!j.current.content.hasClassName("new-user")){j.prev.content.show().addClassName("hidden-tab");j.prev.content.next("div.overlay").setStyle({display:"block"})}else{j.current.content.removeClassName("hidden-tab");j.current.content.next("div.overlay").hide()}var k=$(j.prev.content),i=k.select("ul.messages")[0];i&&i.hide()};for(var b in h){c.setTabForm(b,new RestorableForm(h[b]))}var f=c.getCurrentForm();if(f&&a){for(var g in a){f.addEmbededForm(a[g])}}if(e=(location.hash||" ").substr(1)){c.setCurrentTab(e)}return c},createDiscount:function(b){var a=new VarienForm(b);a.submit=function(c){if(c){$(this.form.coupon_code).removeClassName("required-entry");$(this.form.remove).value="1"}else{$(this.form.coupon_code).addClassName("required-entry");$(this.form.remove).value="0"}return VarienForm.prototype.submit.call(this)};if(a.form){a.form.select("button").each(function(c){c.observe("click",function(){a.submit(this.name=="cancel")})})}},createForms:function(c){var b=c.length,a={};while(b--){a[c[b]]=new RestorableForm(c[b])}return a},clearStorage:function(b,a){var d="",c="";for(d in b){c=b[d];if(c.clearValues&&!c.form){c.clearValues(a)}}},getLocationBox:function(d,a,c){var b=null;if(d){b=new FreaksAutocompleter(d.previous("input[id]"),d,d.parentNode.getAttribute("data-action"),{paramName:"location",indicator:"input-loading",minChars:2,onShow:function(e,f){Effect.Appear(f,{duration:0.15})}});b.addShowResults("a.select-tip")}else{if(c){b=new fiSelectBox($(c[a.box]));b.dependsOn($(c[a.dependsOn]),"country_id","input-loading")}}return b},observeAddressFields:function(a,e,d){if(!d){return}var b=a.length,c="";while(b--){c=$(d["user[address]["+a[b]+"]"]);if(c){c.observe("change",e)}}},createCheckout:function(c,b){var a=this;var d=c.getCurrentForm().getEmbededForm("payment-form");$$('form[name="user_info"]').each(function(h){var f=h.select("div.shipping-methods")[0];var e=new fiCheckoutViewModel({shipping:f,payment:d.form,totals:b});if(f){f.observe("click",function(k){var j=Event.element(k);if(!fiCheckoutViewModel.isElement(j,"radio")){return true}this.updateSection("totals",{shipping_method:j.value});this.disableButton("place-order")}.bindAsEventListener(e))}if(e.isActive()){var i=e.getChangeListener();var g=a.getLocationBox(h.select("div.autocomplete")[0],{box:"user[address][region_id]",dependsOn:"user[address][country_id]"},h);g&&g.addChangeListener(i);a.observeAddressFields(["country_id","postcode"],i,h)}if(d&&d.form&&d.form.getAttribute("data-update-totals")){d.form.observe("click",function(j){var k=Event.element(j);if(!fiCheckoutViewModel.isElement(k,"radio")){return true}params={};params[k.name]=k.value;this.updateSection("totals",params);this.disableButton("place-order")}.bindAsEventListener(e))}});$(document).observe("fiCheckout:updated",function(e){d.restoreValues();e.memo.viewModel.enableButton("place-order")})},wakeUpPayment:function(d,c){var a=new fiPaymentViewModel(d),e=new fiCentinelViewModel(c);if(a.element){a.element.observe("click",function(g){var f=Event.element(g);if(!fiCheckoutViewModel.isElement(f,"radio")){return true}if(a.getCurrentMethod()!=f.value){a.switchMethod(f.value);e.idle()}});$(document).observe("fiCheckout:updated",function(){a.restore()})}if(e.element){var b=function(){fiCheckoutViewModel.prototype.enableButton("place-order")};e.element.observe("fiCentinel:success",b);e.element.observe("fiCentinel:cancel",b)}return{payment:a,centinel:e}},send:function(b,d,c,a){if(!b){return false}var f=d.getCurrentForm()&&d.getCurrentForm().getEmbededForm("payment-form");b.observe("click",function(i){var k=c.payment.getCurrentMethodElement(),g=true,h="";if(k){h=k.getAttribute("data-action");if(h&&!c.centinel.isSuccessfull()){var j=Form.serialize(c.payment.getAdditionalForm(),true);j[k.name]=k.value;if(f.validator.validate()){c.centinel.validate(h,j);fiCheckoutViewModel.prototype.disableButton(this)}g=false}}if(g&&d.getCurrentForm().submit(true)){fiCheckoutViewModel.prototype.processing(this)}});var e=d.getCurrentForm();if(e.form){e.form.observe("form:has_response",function(h){fiCheckoutViewModel.prototype.stopProcessing(b);var g=h.memo;if(g.error_message){c.centinel.resetValidation();fiCheckoutViewModel.prototype.enableButton(b)}if(!g.update_section){return true}content.up().show();content.update(g.update_section.html);try{g.update_section.html.evalScripts()}catch(i){content.select("iframe").each(function(j){if(!j.offsetWidth){j.observe("load",j.show.bind(j))}})}})}return true}});Validator.prototype.test=(function(a){return function(c,d){var b=a.apply(this,arguments);if(this.options.onElementValidate){this.options.onElementValidate.call(this,b,d)}return b}})(Validator.prototype.test);Object.extend(Validation.get("validate-state").options,{onElementValidate:function(a,b){var c=$(b).previous('input[type="text"]');if(a){c.removeClassName("validation-failed").addClassName("validation-passed")}else{c.addClassName("validation-failed").revemoClassName("validation-passed")}}});$(document).observe("dom:loaded",function(){var e=new fiPage(),a=e.createForms(["payment-form","shipping-method-load"]),f=new VarienForm("checkout-login-form");a["checkout-agreements"]=new VarienForm("checkout-agreements");a["newsletter-form"]=new VarienForm("newsletter-form");var c=e.createTabs($$("#checkout-block ul.infos-menus")[0],{"new-user":"new-user-form","registered-user":"registered-user-form"},a);e.createDiscount("discount-coupon-form");e.createCheckout(c,$("shopping-cart-totals-table"));var d=$$("#payment-popup div.popup-content")[0],b=e.wakeUpPayment($("payment-methods"),d);e.send($("place-order"),c,b,d);window.CentinelAuthenticateController=b.centinel});