(function() {
    var app = angular.module('bs_zones', []);

    app.controller('ZoneCtrl', function($scope, $http) {
        var bootstrap = window.bs_bootstrap;
        $scope.loadingMessage = null;

        $scope.data = { settings: bootstrap.settings || {} };

        var catList = [], found = false;
        for(var i = 0; i < bootstrap.categories.length; i++) {
            catList.push({name: bootstrap.categories[i].cat_name, id: bootstrap.categories[i].cat_ID, selected: false, ticked: false});
        }

        $scope.data.categories = catList;

        $scope.save = function() {
            console.log($scope.data.settings);
            $scope.loadingMessage = 'Saving ...';
            var params = $scope.data.settings;
            $http.post(window.ajaxurl + '?action=partyline_save_settings', params)
                .success(function(response) {
                    $scope.loadingMessage = null;
                }).error(function(response) {
                    $scope.loadingMessage = null;
                    alert('There was an error saving the zone information! Try again.');
                });
        }

        if (!$scope.data.settings.partyline_key) {
            $scope.data.settings.partyline_key = Math.random().toString(36).substring(2, 15);
            $scope.save();
        }
    });

    window.copyToClipboard = function(element) {
        var $temp = jQuery("<input>");
        jQuery("body").append($temp);
        $temp.val(jQuery(element).text()).select();
        document.execCommand("copy");
        $temp.remove();
        alert("Copied to clipboard!");
    }
})();