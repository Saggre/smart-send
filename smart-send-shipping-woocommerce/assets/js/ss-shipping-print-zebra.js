function printPdfOnZebra(url) {

    BrowserPrint.getDefaultDevice('printer', function(printer)
        {
            if((typeof printer != "undefined") && (printer.connection == undefined))
            {
                alert("No default printer found. " + "Setup a default printer in Zebra Browser Print.");
            }
            else{
                //alert("Printer name: " + printer.name);

                printer.sendUrl(
                    url,
                    function(success_response) {
                        //alert("Printed succesfully on printer " + printer.name);
                    },
                    function(error_response) {
                        alert("Failed to print on printer " + printer.name);
                    }
                );
            }
        },

        function(error_response)
        {
            // This alert doesn't pop either
            alert("An error occured while attempting to connect to your Zebra Printer. " +
                "You may not have Zebra Browser Print installed, or it may not be running. " +
                "Install Zebra Browser Print, or start the Zebra Browser Print Service, and try again.");
        }
    );
}

function checkZebraPrinterStatus() {

    BrowserPrint.getDefaultDevice('printer', function(printer)
    {
        printer.sendThenRead("~HQES", function(text){
                var that = this;
                var statuses = new Array();
                var ok = false;
                var is_error = text.charAt(70);
                var media = text.charAt(88);
                var head = text.charAt(87);
                var pause = text.charAt(84);

                // check each flag that prevents printing
                if (is_error == '0')
                {
                    ok = true;
                    statuses.push("Ready to Print");
                }

                if (media == '1')
                    statuses.push("Paper out");

                if (media == '2')
                    statuses.push("Ribbon Out");

                if (media == '4')
                    statuses.push("Media Door Open");

                if (media == '8')
                    statuses.push("Cutter Fault");

                if (head == '1')
                    statuses.push("Printhead Overheating");

                if (head == '2')
                    statuses.push("Motor Overheating");

                if (head == '4')
                    statuses.push("Printhead Fault");

                if (head == '8')
                    statuses.push("Incorrect Printhead");

                if (pause == '1')
                    statuses.push("Printer Paused");

                if ((!ok) && (statuses.Count == 0))
                    statuses.push("Error: Unknown Error");

                alert(statuses.join());

            }, function(error_response)
            {
                // This alert doesn't pop either
                alert("An unexpected error occured");
            }
        );
    });
};