(function(){
    var menus=Array.from(document.querySelectorAll('.module-nav'));
    menus.forEach(function(menu){menu.addEventListener('toggle',function(){if(menu.open)menus.forEach(function(other){if(other!==menu)other.open=false;});});});
    document.addEventListener('click',function(event){menus.forEach(function(menu){if(!menu.contains(event.target))menu.open=false;});});
    document.addEventListener('keydown',function(event){if(event.key==='Escape')menus.forEach(function(menu){if(menu.open){menu.open=false;menu.querySelector('summary').focus();}});});
})();
