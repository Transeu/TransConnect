using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using ApiClient;

namespace test
{
	class Program
	{
		static void Main(string[] args)
		{
			var ServerAddr = "https://api.trans.eu";

			var ApiKey = "";
			var SecretKey = "";

			// ADD PARAMS LOAD 
			var Class = "User";
			var Method = "authorize";

			// ADD PARAMS AUTHORIZE 
			//var Class = "Offer";
			//var Method = "add";

			JSONRPC2ClientSecure client = new JSONRPC2ClientSecure(ServerAddr + "/?class=Trans_Api_Public_" + Class, ApiKey, SecretKey);

			//client.setProxy(""); // proxy:port
			//client.setProxyAuthentication("",""); // login, pass
		
			
			/*------------- SECTION AUTH ----------------------*/
			
			object[] arguments = new object[2];
			arguments[0] = ""; // TransId
			arguments[1] = ""; // Password
			
			var json = client.InvokeMethod(Method,arguments);
			
			/*------------------ END SECTION AUTH --------------*/

			/* ----- SECTION ADD LOAD ----- */
			//http://doc.api.trans.eu/class_trans___api___public___offer.html#a28ecd44600c7f46a988dbe5de5985611
			/*
			var t = new System.Collections.Hashtable();
			t.Add("offerType","1");
			t.Add("countryFrom","88");
			t.Add("countryTo","88");
			t.Add("postcodeFrom","58-100");
			t.Add("postcodeTo","42-100");
			
			t.Add("dateLoading","05.06.2013 13:21");
			t.Add("dateUnloading","09.06.2013 16:51");
			t.Add("truckBody","1");
			t.Add("capacity","6.66");
			t.Add ("receiverType","all");
			
			t.Add("ownerTransId","TransId");
			t.Add ("contactUsers",new string[] { "TransId", "TransId" });

			var json = client.InvokeMethod(Method,new object[]{t});
			*/

			/*------------ END SECTION ADD LOAD --------------*/

			System.Console.WriteLine("response:  "+json);
			//System.Console.ReadKey();
		}
	}
}
