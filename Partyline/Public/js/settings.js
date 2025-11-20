(function() {
    var app = angular.module('bs_zones', []);

    app.controller('ZoneCtrl', function($scope, $http) {
        $scope.loadingMessage = null;
        $scope.formDisabled = true;
        $scope.settings_nonce = '';

        // Get wordpress settings
        $http.get(window.ajaxurl + '?action=partyline_get_settings', {
            withCredentials: true // include logged-in cookies
        }).then(function (response) {
            var bootstrap = response.data.data;

            $scope.settings_nonce = bootstrap.nonce; // store wp nonce for later use

            $scope.data = { settings: bootstrap.settings || {} };

            var catList = [], found = false;
            for(var i = 0; i < bootstrap.categories.length; i++) {
                catList.push({name: bootstrap.categories[i].cat_name, id: bootstrap.categories[i].cat_ID, selected: false, ticked: false});
            }

            $scope.data.categories = catList;

            if (parseInt(bootstrap.ok) === 1) {
                // Ready for input
                $scope.formDisabled = false;
            }
            else {
                $scope.formDisabled = true;
            }

            $scope.save = function() {
                $scope.loadingMessage = 'Saving ...';
                var params = $scope.data.settings;
                $scope.formDisabled = true;
                params.nonce = $scope.settings_nonce;

                $http.post(window.ajaxurl + '?action=partyline_save_settings', params)
                    .success(function(response) {
                        $scope.loadingMessage = null;
                        $scope.formDisabled = false;
                    }).error(function(response) {
                        $scope.loadingMessage = null;
                        alert('There was an error saving the zone information! Try again.');
                    });
            }

            if (!$scope.data.settings.partyline_key) {
                $scope.data.settings.partyline_key = Math.random().toString(36).substring(2, 15);
                $scope.save();
            }

        }).catch(function (err) {
            console.error('Error fetching settings:', err);
        });
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