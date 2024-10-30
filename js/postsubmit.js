function postSubmit() {
  this.frmObject = document.createElement("form");
  this.frmObject.method = "post";

  this.add = function(elementname, elementvalue) {
     var input = document.createElement("input");
     input.type = "hidden";
     input.name = elementname;
     input.value = elementvalue;
     this.frmObject.appendChild(input);
     this.frmObject.method = "post";
  };

  this.submit = function(url, targetFrame) {
    try {
      if (targetFrame) {
        this.frmObject.target = targetFrame;
      }
    } catch (e) { }
    
    try {
      if (url) {
        this.frmObject.action = url;
        document.body.appendChild(this.frmObject);
        this.frmObject.submit();
        return true;
      } else { return false; }
    } catch (e) {
       return false;
    }
  };
};
