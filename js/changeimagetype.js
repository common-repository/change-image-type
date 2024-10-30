function fncOnSubmit(pBtn, pId, pType, pMessage) {
    if (pMessage) {
        if (!window.confirm(pMessage)) {
            return;
        }
    }
    var frm = new postSubmit();
    frm.add(pBtn.name, pBtn.value);
    frm.add('id', pId);
    frm.add('type', pType);
    frm.submit(location.href);

    return;
}
