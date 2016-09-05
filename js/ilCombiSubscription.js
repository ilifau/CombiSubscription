/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

$(function() {

    /**
     * Combined Subscription plugin
     *
     * @author Fred Neumann <fred.neumann@fau.de>
     * @version $Id$
     */
    il.CombiSubscription = new function() {

        /**
         * Self reference for usage in event handlers
         * @type object
         * @private
         */
        var self = this;

        self.prioColors = [];


        /**
         * Initialisation
         */
        this.init = function (colors) {

            self.prioColors = colors;

            var rows = $('.ilCombiSubscriptionRow');
            var cols = $('.ilCombiSubscriptionRow td');
            cols.each(function(){
                $(this).attr('data-orig-color', $(this).css('background-color'));
            });

            rows.each(function() {
                self.setColor(this);
                $(this).change(self.choiceChanged);
            });
        };


        /**
         * Field value is changed
         */
        this.choiceChanged = function () {
            self.setColor($(this));
        };

        /**
         * Set the color of a row according to its choice
         */
        this.setColor = function (row) {
            var prio = $(row).find('input:checked').val();

            $(row).children('td').each(function(){
                if (self.prioColors[prio]) {
                   $(this).css('background-color',self.prioColors[prio]);
                }
                else {
                    $(this).css('background-color',$(this).attr('data-orig-color'));
                }
            });
        };

    }
});